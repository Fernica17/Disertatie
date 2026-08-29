<?php

namespace App\Service;

use App\Entity\Notifications;
use App\Entity\Users;
use App\Repository\NotificationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationsRepository $notificationsRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function createNotification(
        string $title,
        string $type,
        ?Users $user = null,
        ?string $userChannel = null,
        array $meta = [],
    ): Notifications {
        $notification = new Notifications();

        if ($userChannel === null && $user !== null) {
            $userChannel = $user->getEmail();
        }

        $notification->setUser($user)
            ->setTitle($title)
            ->setType($type)
            ->setUserChannel($userChannel)
            ->setMeta($meta);

        $this->entityManager->beginTransaction();
        try {
            $this->entityManager->persist($notification);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Notification created', [
                'id' => $notification->getId(),
                'type' => $type,
                'title' => $title,
            ]);

            return $notification;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to create notification', [
                'error' => $e->getMessage(),
                'type' => $type,
                'title' => $title,
            ]);
            throw $e;
        }
    }

    public function markAsRead(Notifications $notification): void
    {
        $this->entityManager->beginTransaction();
        try {
            $notification->setReadAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to mark notification as read', [
                'id' => $notification->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function markAllAsRead(Users $user): void
    {
        $this->entityManager->beginTransaction();
        try {
            $this->notificationsRepository->createQueryBuilder('n')
                ->update()
                ->set('n.readAt', ':now')
                ->where('n.user = :user')
                ->andWhere('n.readAt IS NULL')
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to mark all notifications as read', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
