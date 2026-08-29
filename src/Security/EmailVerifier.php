<?php

namespace App\Security;

use App\Entity\Notifications;
use App\Entity\Users;
use App\Service\NotificationsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly NotificationsService $notificationsService,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sendEmailConfirmation(string $verifyEmailRouteName, UserInterface $user): void
    {
        if (!$user instanceof Users) {
            throw new BadRequestHttpException();
        }

        $context = $this->buildVerificationContext($verifyEmailRouteName, $user);

        $this->notificationsService->createNotification(
            title: $this->translator->trans('notifications.user_onboarding.title', [], 'notifications'),
            type: Notifications::TYPE_USER_ONBOARDING,
            user: $user,
            userChannel: $user->getEmail(),
            meta: $context
        );
    }

    public function resendEmailConfirmation(string $verifyEmailRouteName, UserInterface $user): void
    {
        if (!$user instanceof Users) {
            throw new BadRequestHttpException();
        }

        $context = $this->buildVerificationContext($verifyEmailRouteName, $user);

        $this->notificationsService->createNotification(
            title: $this->translator->trans('notifications.resend_verification.title', [], 'notifications'),
            type: Notifications::TYPE_RESEND_VERIFICATION,
            user: $user,
            userChannel: $user->getEmail(),
            meta: $context
        );
    }

    private function buildVerificationContext(string $verifyEmailRouteName, Users $user): array
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        return [
            'signedUrl' => $signatureComponents->getSignedUrl(),
            'expiresAt' => $signatureComponents->getExpirationMessageKey(),
            'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
        ];
    }

    public function handleEmailConfirmation(Request $request, UserInterface $user): void
    {
        if (!$user instanceof Users) {
            throw new BadRequestHttpException();
        }

        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());

        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
