<?php

namespace App\Controller\Admin;

use App\Entity\Notifications;
use App\Entity\Users;
use App\Enum\NotificationHeaderType;
use App\Repository\NotificationsRepository;
use App\Service\NotificationsService;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class NotificationHeaderController extends AbstractController
{
    public function __construct(
        private readonly NotificationsService $notificationsService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function renderDropdown(
        NotificationsRepository $notificationsRepository,
    ): Response {
        /** @var Users $user */
        $user = $this->getUser();

        $rawNotifications = $notificationsRepository->getUnreadNotifications($user);
        $unreadCount = $notificationsRepository->countUnreadNotifications($user);

        $processedNotifications = [];

        foreach ($rawNotifications as $notif) {
            $headerType = NotificationHeaderType::tryFrom($notif->getType());

            $processedNotifications[] = [
                'id' => $notif->getId(),
                'title' => $notif->getTitle() ?? $this->translator->trans('notifications.header.new_notification', [], 'notifications'),
                'url' => $this->generateUrl('admin_notification_read', ['id' => $notif->getId()]),
                'icon' => $headerType ? $headerType->getConfig()['icon'] : 'fa-solid fa-bell',
                'color' => $headerType ? $headerType->getConfig()['color'] : 'info',
                'isRead' => false,
                'createdAt' => $notif->getCreatedAt(),
            ];
        }

        return $this->render('admin/notifications/_notifications.html.twig', [
            'notifications' => $processedNotifications,
            'unreadCount' => $unreadCount,
            'viewAllUrl' => $this->generateUrl('admin_notifications_index'),
        ]);
    }

    #[Route('/admin/notification/{id}/read', name: 'admin_notification_read', requirements: ['id' => '\d+'])]
    public function readAndRedirect(
        Notifications $notification,
        AdminUrlGenerator $adminUrlGenerator,
    ): Response {
        if ($notification->getUser() === $this->getUser() && $notification->getReadAt() === null) {
            $this->notificationsService->markAsRead($notification);
        }

        $targetUrl = $this->buildTargetUrl($notification, $adminUrlGenerator);

        return $this->redirect($targetUrl);
    }

    #[Route('/admin/read-all', name: 'admin_notifications_read_all')]
    public function readAll(
        Request $request,
    ): Response {
        /** @var Users $user */
        $user = $this->getUser();

        $this->notificationsService->markAllAsRead($user);

        $fallbackUrl = $this->generateUrl('admin');
        $referer = $request->headers->get('referer', $fallbackUrl);

        if (!$this->isLocalUrl($referer)) {
            $referer = $fallbackUrl;
        }

        return $this->redirect($referer);
    }

    private function buildTargetUrl(Notifications $notification, AdminUrlGenerator $adminUrlGenerator): string
    {
        $headerType = NotificationHeaderType::tryFrom($notification->getType());

        if (!$headerType) {
            return $this->generateUrl('admin');
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

        $gen = $adminUrlGenerator->unsetAll()->setController($config['controller']);

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

    private function isLocalUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        $parsedHost = parse_url($url, PHP_URL_HOST);

        return $parsedHost === null || $parsedHost === $_SERVER['HTTP_HOST'];
    }
}
