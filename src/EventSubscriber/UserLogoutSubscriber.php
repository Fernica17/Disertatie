<?php

namespace App\EventSubscriber;

use App\Entity\Users;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Users) {
            return;
        }

        $isExternalUser = $this->security->isGranted('ROLE_CLIENT');
        $shouldLogout = false;
        $company = $user->getCompany();

        if (!$user->isActive()) {
            $shouldLogout = true;
        } elseif ($isExternalUser && $company === null) {
            $shouldLogout = true;
        } elseif ($company !== null && !$company->isActive()) {
            $shouldLogout = true;
        }

        if ($shouldLogout) {
            $this->security->logout(false);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
        }
    }
}
