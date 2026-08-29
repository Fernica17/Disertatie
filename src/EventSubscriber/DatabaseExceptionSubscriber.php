<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DatabaseExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException'],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($this->isForeignKeyConstraintException($exception)) {
            $request = $event->getRequest();
            $session = $this->requestStack->getSession();

            $errorMessage = $this->translator->trans(
                'error.foreign_key_violation',
                [],
                'admin'
            );
            if ($session instanceof Session) {
                $session->getFlashBag()->add('danger', $errorMessage);
            }
            $referer = $request->headers->get('referer');
            $url = $referer ?: $this->urlGenerator->generate('admin');

            $response = new RedirectResponse($url);
            $event->setResponse($response);
        }
    }

    private function isForeignKeyConstraintException(\Throwable $exception): bool
    {
        if ($exception instanceof ForeignKeyConstraintViolationException) {
            return true;
        }

        if ($exception->getPrevious() instanceof ForeignKeyConstraintViolationException) {
            return true;
        }

        return false;
    }
}
