<?php

namespace App\EventSubscriber\Audit;

use App\Audit\Entity\EntityChangeLogs;
use App\Entity\Users;
use App\Message\Audit\LogEntityChangeMessage;
use App\Service\Audit\DataSanitizerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::onFlush, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, connection: 'default')]
class EntityChangeLogSubscriber
{
    private const array IGNORED_FIELDS = [
        'updatedAt',
        'createdAt',
        'password',
        'plainPassword',
        'hashedToken',
        'slug',
    ];

    private const array IGNORED_ENTITIES = [
        'Settings',
        'Notifications',
    ];

    /** @var LogEntityChangeMessage[] */
    private array $pendingMessages = [];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        #[Autowire('%audit.enabled%')]
        private readonly bool $enabled,
        private readonly RequestStack $requestStack,
        private readonly DataSanitizerService $dataSanitizer,
        #[Autowire('%audit.anonymize_ip%')]
        private readonly bool $anonymizeIp,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $em = $event->getObjectManager();
            $uow = $em->getUnitOfWork();

            $user = $this->security->getUser();
            $userId = $user instanceof Users ? $user->getId() : null;
            $username = $user?->getUserIdentifier();

            $ipAddress = $this->getIpAddress($this->requestStack->getCurrentRequest());
            // Inserts
            foreach ($uow->getScheduledEntityInsertions() as $entity) {
                $className = $this->getShortClassName($entity);
                if ($this->isIgnoredEntity($className)) {
                    continue;
                }

                $this->pendingMessages[] = new LogEntityChangeMessage(
                    entityClass: $className,
                    entityId: 0, // will be resolved in postFlush
                    action: EntityChangeLogs::ACTION_CREATE,
                    changes: $this->extractEntityData($entity, $em),
                    userId: $userId,
                    username: $username,
                    entityLabel: $this->getEntityLabel($entity),
                    ipAddress: $ipAddress,
                );
            }

            // Updates
            foreach ($uow->getScheduledEntityUpdates() as $entity) {
                $className = $this->getShortClassName($entity);
                if ($this->isIgnoredEntity($className)) {
                    continue;
                }

                $changeSet = $uow->getEntityChangeSet($entity);
                $changes = $this->formatChangeSet($changeSet);

                if (empty($changes)) {
                    continue;
                }

                $entityId = $this->getEntityId($entity);
                if ($entityId === null) {
                    continue;
                }

                $this->pendingMessages[] = new LogEntityChangeMessage(
                    entityClass: $className,
                    entityId: $entityId,
                    action: EntityChangeLogs::ACTION_UPDATE,
                    changes: $changes,
                    userId: $userId,
                    username: $username,
                    entityLabel: $this->getEntityLabel($entity),
                    ipAddress: $ipAddress,
                );
            }

            // Deletes
            foreach ($uow->getScheduledEntityDeletions() as $entity) {
                $className = $this->getShortClassName($entity);
                if ($this->isIgnoredEntity($className)) {
                    continue;
                }

                $entityId = $this->getEntityId($entity);
                if ($entityId === null) {
                    continue;
                }

                $this->pendingMessages[] = new LogEntityChangeMessage(
                    entityClass: $className,
                    entityId: $entityId,
                    action: EntityChangeLogs::ACTION_DELETE,
                    changes: null,
                    userId: $userId,
                    username: $username,
                    entityLabel: $this->getEntityLabel($entity),
                    ipAddress: $ipAddress,
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to capture entity changes', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if (empty($this->pendingMessages)) {
            return;
        }

        $messages = $this->pendingMessages;
        $this->pendingMessages = [];

        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        try {
            foreach ($messages as $message) {
                // Resolve entity ID for inserts (now available after flush)
                if ($message->action === EntityChangeLogs::ACTION_CREATE && $message->entityId === 0) {
                    $entityId = $this->resolveInsertedEntityId($message->entityClass, $message->entityLabel, $uow, $em);
                    if ($entityId !== null) {
                        $message = new LogEntityChangeMessage(
                            entityClass: $message->entityClass,
                            entityId: $entityId,
                            action: $message->action,
                            changes: $message->changes,
                            userId: $message->userId,
                            username: $message->username,
                            entityLabel: $message->entityLabel,
                            ipAddress: $message->ipAddress,
                        );
                    }
                }

                $this->messageBus->dispatch($message);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to dispatch entity change messages', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatChangeSet(array $changeSet): array
    {
        $changes = [];

        foreach ($changeSet as $field => [$oldValue, $newValue]) {
            if (in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }

            $old = $this->normalizeValue($oldValue);
            $new = $this->normalizeValue($newValue);

            if ($old === $new) {
                continue;
            }

            $changes[$field] = [
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    private function extractEntityData(object $entity, object $em): array
    {
        $data = [];
        $metadata = $em->getClassMetadata($entity::class);

        foreach ($metadata->getFieldNames() as $field) {
            if (in_array($field, self::IGNORED_FIELDS, true) || $field === 'id') {
                continue;
            }

            $value = $metadata->getFieldValue($entity, $field);
            $data[$field] = ['old' => null, 'new' => $this->normalizeValue($value)];
        }

        foreach ($metadata->getAssociationNames() as $assoc) {
            if (!$metadata->isSingleValuedAssociation($assoc)) {
                continue;
            }

            $related = $metadata->getFieldValue($entity, $assoc);
            if ($related !== null && method_exists($related, 'getId')) {
                $data[$assoc] = ['old' => null, 'new' => $related->getId()];
            }
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value) && method_exists($value, 'getId')) {
            return $value->getId();
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        if (is_object($value)) {
            return $value::class;
        }

        return $value;
    }

    private function getShortClassName(object $entity): string
    {
        $class = $entity::class;

        // Remove Doctrine proxy prefix
        if (str_contains($class, '\\__CG__\\')) {
            $class = substr($class, strrpos($class, '\\') + 1);
        } else {
            $class = substr($class, strrpos($class, '\\') + 1);
        }

        return $class;
    }

    private function isIgnoredEntity(string $className): bool
    {
        return in_array($className, self::IGNORED_ENTITIES, true);
    }

    private function getEntityId(object $entity): ?int
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();

            return is_int($id) ? $id : null;
        }

        return null;
    }

    private function getEntityLabel(object $entity): ?string
    {
        if (method_exists($entity, '__toString')) {
            try {
                $label = (string) $entity;

                return $label !== '' ? mb_substr($label, 0, 255) : null;
            } catch (\Exception) {
                return null;
            }
        }

        return null;
    }

    private function resolveInsertedEntityId(string $entityClass, ?string $entityLabel, object $uow, object $em): ?int
    {
        $identityMap = $uow->getIdentityMap();

        foreach ($identityMap as $className => $entities) {
            $shortName = substr($className, strrpos($className, '\\') + 1);
            if ($shortName !== $entityClass) {
                continue;
            }

            // Get the last inserted entity of this type
            $lastEntity = end($entities);
            if ($lastEntity && method_exists($lastEntity, 'getId')) {
                return $lastEntity->getId();
            }
        }

        return null;
    }

    private function getIpAddress(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $ip = $request->getClientIp();

        if ($ip === null || !$this->anonymizeIp) {
            return $ip;
        }

        return $this->dataSanitizer->anonymizeIp($ip);
    }
}
