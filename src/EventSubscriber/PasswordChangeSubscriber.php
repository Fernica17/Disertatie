<?php

namespace App\EventSubscriber;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordChangeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        $allowedRoutes = [
            'app_change_password',
            'app_logout',
            '_wdt',
            '_profiler',
        ];

        if (in_array($currentRoute, $allowedRoutes, true)) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof Users && $user->isChangePasswordRequired()) {
            $url = $this->urlGenerator->generate('app_change_password');

            $event->setResponse(new RedirectResponse($url));
        }
    }
}
