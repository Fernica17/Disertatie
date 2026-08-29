<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\Type\ChangePasswordType;
use App\Service\UsersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangePasswordController extends AbstractController
{
    public function __construct(
        private readonly UsersService $usersService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/change-password', name: 'app_change_password')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isChangePasswordRequired()) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('plainPassword')->getData();

            $this->usersService->changePassword($user, $newPassword);

            $this->addFlash('success', $this->translator->trans('security.change_password.success', [], 'security'));

            return $this->redirectToRoute('admin');
        }

        return $this->render('security/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
