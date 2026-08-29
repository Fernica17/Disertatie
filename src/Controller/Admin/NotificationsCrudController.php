<?php

namespace App\Controller\Admin;

use App\Entity\Notifications;
use App\Enum\NotificationHeaderType;
use App\Service\NotificationsService;
use App\Traits\ExportableCrudTrait;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class NotificationsCrudController extends AbstractCrudController
{
    use ExportableCrudTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly NotificationsService $notificationsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Notifications::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->translator->trans('notifications.crud.singular', [], 'notifications'))
            ->setEntityLabelInPlural($this->translator->trans('notifications.crud.plural', [], 'notifications'))
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $this->addExportAction($actions);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function detail(AdminContext $context): Response
    {
        /** @var Notifications $notification */
        $notification = $context->getEntity()->getInstance();

        if ($notification->getReadAt() === null) {
            $this->notificationsService->markAsRead($notification);
        }

        return $this->redirect($this->generateTargetUrl($notification));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title', $this->translator->trans('notifications.crud.field.title', [], 'notifications')),

            TextField::new('type', $this->translator->trans('notifications.crud.field.type', [], 'notifications'))
                ->formatValue(function ($value) {
                    return str_replace('_', ' ', ucfirst($value));
                }),

            TextField::new('userChannel', $this->translator->trans('notifications.crud.field.channel', [], 'notifications'))
                ->hideOnIndex(),

            DateTimeField::new('createdAt', $this->translator->trans('notifications.crud.field.created_at', [], 'notifications'))
                ->setFormat('dd.MM.yyyy HH:mm'),

            DateTimeField::new('readAt', $this->translator->trans('notifications.crud.field.read_at', [], 'notifications'))
                ->setFormat('dd.MM.yyyy HH:mm')
                ->formatValue(function ($value) {
                    if ($value === null) {
                        return '<span class="badge badge-danger">' . $this->translator->trans('notifications.crud.field.unread', [], 'notifications') . '</span>';
                    }

                    return $value->format('d.m.Y H:i');
                }),
        ];
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $user = $this->getUser();

        if ($user) {
            $qb->andWhere('entity.user = :user')
                ->setParameter('user', $user)
                ->andWhere('entity.type IN (:included_types)')
                ->setParameter('included_types', NotificationHeaderType::getAllowedTypes());
        }

        return $qb;
    }

    private function generateTargetUrl(Notifications $notification): string
    {
        $headerType = NotificationHeaderType::tryFrom($notification->getType());

        if (!$headerType) {
            return $this->adminUrlGenerator->unsetAll()->setRoute('admin')->generateUrl();
        }

        $config = $headerType->getConfig();
        $meta = $notification->getMeta() ?? [];

        $allItems = [];
        $targetArrays = $config['target_array'] ?? null;

        if ($targetArrays !== null) {
            foreach ((array) $targetArrays as $key) {
                if (isset($meta[$key]) && is_array($meta[$key])) {
                    $allItems = array_merge($allItems, $meta[$key]);
                }
            }
        } else {
            $allItems = $meta;
        }

        $gen = $this->adminUrlGenerator->unsetAll()->setController($config['controller']);

        if (count($allItems) === 1) {
            $entityId = $this->findIdInMeta($allItems, $config['search_keys']);

            if ($entityId) {
                return $gen->setAction('detail')->setEntityId($entityId)->generateUrl();
            }
        }

        return $gen->setAction('index')->generateUrl();
    }

    private function findIdInMeta(array $data, array $searchKeys): ?int
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $searchKeys, true) && is_numeric($value)) {
                return (int) $value;
            }

            if (is_array($value)) {
                $found = $this->findIdInMeta($value, $searchKeys);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
