<?php

namespace App\EventSubscriber\Audit;

use App\Audit\Entity\AuthenticationLogs;
use App\Entity\Users;
use App\Message\Audit\LogAuthenticationMessage;
use App\Service\Audit\DataSanitizerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly DataSanitizerService $dataSanitizer,
        #[Autowire('%audit.enabled%')]
        private readonly bool $enabled,
        #[Autowire('%audit.anonymize_ip%')]
        private readonly bool $anonymizeIp,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $user = $event->getUser();
            $request = $event->getRequest();

            $this->dispatch(
                userId: $user instanceof Users ? $user->getId() : null,
                username: $user->getUserIdentifier(),
                eventType: AuthenticationLogs::TYPE_LOGIN_SUCCESS,
                ipAddress: $this->getIpAddress($request),
                userAgent: $request->headers->get('User-Agent'),
                success: true,
                sessionId: $request->getSession()->getId(),
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log authentication success', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $request = $event->getRequest();
            $exception = $event->getException();

            $email = $request->request->get('_username')
                ?? $request->request->all('login_form')['_username']
                ?? null;

            $this->dispatch(
                userId: null,
                username: $email,
                eventType: AuthenticationLogs::TYPE_LOGIN_FAILED,
                ipAddress: $this->getIpAddress($request),
                userAgent: $request->headers->get('User-Agent'),
                success: false,
                failureReason: $exception->getMessageKey(),
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log authentication failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $token = $event->getToken();
            $user = $token?->getUser();
            $request = $event->getRequest();

            $this->dispatch(
                userId: $user instanceof Users ? $user->getId() : null,
                username: $user?->getUserIdentifier(),
                eventType: AuthenticationLogs::TYPE_LOGOUT,
                ipAddress: $this->getIpAddress($request),
                userAgent: $request->headers->get('User-Agent'),
                success: true,
                sessionId: $request->hasSession() ? $request->getSession()->getId() : null,
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to log logout event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function dispatch(
        ?int $userId,
        ?string $username,
        string $eventType,
        ?string $ipAddress,
        ?string $userAgent,
        bool $success,
        ?string $failureReason = null,
        ?string $sessionId = null,
        ?array $additionalData = null,
    ): void {
        $this->messageBus->dispatch(new LogAuthenticationMessage(
            userId: $userId,
            username: $username,
            eventType: $eventType,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            success: $success,
            failureReason: $failureReason,
            sessionId: $sessionId,
            additionalData: $additionalData,
        ));
    }

    private function getIpAddress(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        $ip = $request->getClientIp();

        if ($ip === null || !$this->anonymizeIp) {
            return $ip;
        }

        return $this->dataSanitizer->anonymizeIp($ip);
    }
}
