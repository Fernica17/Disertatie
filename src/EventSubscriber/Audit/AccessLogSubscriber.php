<?php

namespace App\EventSubscriber\Audit;

use App\Entity\Users;
use App\Message\Audit\LogAccessMessage;
use App\Service\Audit\DataSanitizerService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;

class AccessLogSubscriber implements EventSubscriberInterface
{
    private const array EXCLUDED_ROUTES = [
        '_wdt',
        '_profiler',
        '_error',
        'app_login',
        'app_logout',
    ];

    private const array EXCLUDED_PATH_PREFIXES = [
        '/_wdt/',
        '/_profiler/',
        '/bundles/',
        '/build/',
        '/uploads/',
        '/favicon.ico',
    ];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly DataSanitizerService $dataSanitizer,
        private readonly Security $security,
        #[Autowire('%audit.enabled%')]
        private readonly bool $enabled,
        #[Autowire('%audit.anonymize_ip%')]
        private readonly bool $anonymizeIp,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        try {
            $request = $event->getRequest();
            $response = $event->getResponse();

            $user = $this->security->getUser();
            if (!$user instanceof Users) {
                return;
            }

            $path = $request->getPathInfo();
            $symfonyRoute = $request->attributes->get('_route');

            if ($this->isExcluded($symfonyRoute, $path)) {
                return;
            }

            $controller = $request->attributes->get('_controller', '');
            if (!is_string($controller)) {
                $controller = is_array($controller) ? implode('::', array_map(fn ($v) => is_object($v) ? $v::class : (string) $v, $controller)) : '';
            }
            [$controllerClass, $action] = $this->parseController($controller);

            $requestParams = array_merge(
                $request->query->all(),
                $request->request->all()
            );
            $sanitizedParams = !empty($requestParams) ? $this->dataSanitizer->sanitize($requestParams) : null;

            $ip = $request->getClientIp();
            if ($ip !== null && $this->anonymizeIp) {
                $ip = $this->dataSanitizer->anonymizeIp($ip);
            }

            $executionTime = null;
            $requestTimeFloat = $request->server->get('REQUEST_TIME_FLOAT');
            if ($requestTimeFloat) {
                $executionTime = round((microtime(true) - $requestTimeFloat) * 1000, 2);
            }

            $this->messageBus->dispatch(new LogAccessMessage(
                userId: $user->getId(),
                username: $user->getUserIdentifier(),
                userRoles: $user->getRoles(),
                routeName: $path,
                url: $request->getUri(),
                method: $request->getMethod(),
                controller: $controllerClass,
                action: $action,
                requestParams: $sanitizedParams,
                responseCode: $response->getStatusCode(),
                ipAddress: $ip,
                userAgent: $request->headers->get('User-Agent'),
                sessionId: $request->hasSession() ? $request->getSession()->getId() : null,
                referer: $request->headers->get('Referer'),
                executionTime: $executionTime,
                memoryUsage: memory_get_peak_usage(true),
            ));
        } catch (\Exception $e) {
            $this->logger->error('Failed to log access event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isExcluded(?string $routeName, string $path): bool
    {
        if ($routeName !== null) {
            foreach (self::EXCLUDED_ROUTES as $excluded) {
                if ($routeName === $excluded || str_starts_with($routeName, $excluded)) {
                    return true;
                }
            }
        }

        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) || $path === $prefix) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseController(string $controller): array
    {
        if (str_contains($controller, '::')) {
            $parts = explode('::', $controller);

            return [$parts[0], $parts[1] ?? null];
        }

        return [$controller ?: null, null];
    }
}
