<?php

namespace App\Controller;

use App\Entity\Notifications;
use App\Entity\Users;
use App\Form\Type\ChangePasswordType;
use App\Service\NotificationsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationsService $notificationsService,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password', $request->request->get('_csrf_token'))) {
                $this->addFlash('error', $this->translator->trans('security.forgot_password.invalid_csrf', [], 'security'));

                return $this->redirectToRoute('app_forgot_password');
            }

            $email = trim((string) $request->request->get('email'));

            if ($email) {
                $this->processSendingPasswordResetEmail($email);
            }

            $this->addFlash('success', $this->translator->trans('security.forgot_password.success_message', [], 'security'));

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        try {
            /** @var Users $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', $this->translator->trans('security.reset.error_invalid_token', [], 'security'));

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans(
                'security.reset.password_change_success',
                [],
                'security'
            ));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $email): void
    {
        $user = $this->entityManager->getRepository(Users::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->logger->notice('Reset password requested for unknown email', ['email' => $email]);

            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->notice('Reset password throttled', [
                'email' => $user->getEmail(),
                'reason' => $e->getReason(),
            ]);

            return;
        }

        $this->notificationsService->createNotification(
            $this->translator->trans('email.reset_password.subject', [], 'notifications'),
            Notifications::TYPE_USER_RESET_PASSWORD,
            $user,
            null,
            [
                'resetToken' => $resetToken->getToken(),
            ],
        );
    }
}
