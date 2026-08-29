<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Security\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        EmailVerifier $emailVerifier,
        UsersRepository $usersRepository,
    ): Response {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_login');
        }

        $user = $usersRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('danger', $exception->getReason());

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', $this->translator->trans('notifications.verify_email.success', [], 'notifications'));

        $this->security->login($user, 'form_login', 'main');

        return $this->redirectToRoute('admin');
    }

    #[Route('/resend-verification', name: 'app_resend_verification')]
    public function resend(
        Request $request,
        UsersRepository $usersRepository,
        EmailVerifier $emailVerifier,
    ): Response {
        $email = $request->query->get('email');

        if (!$email) {
            $this->addFlash('danger', $this->translator->trans('notifications.resend_verification.missing_email', [], 'notifications'));

            return $this->redirectToRoute('app_login');
        }

        $user = $usersRepository->findOneBy(['email' => $email]);

        if ($user) {
            $emailVerifier->resendEmailConfirmation('app_verify_email', $user);
        }

        $this->addFlash('success', $this->translator->trans('notifications.resend_verification.generic_success', [], 'notifications'));

        return $this->redirectToRoute('app_login');
    }
}
