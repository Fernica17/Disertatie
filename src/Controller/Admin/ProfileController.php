<?php

namespace App\Controller\Admin;

use App\Entity\Files;
use App\Entity\Users;
use App\Form\Type\ProfileChangePasswordType;
use App\Form\Type\ProfileType;
use App\Service\FilesUploadService;
use App\Service\UsersService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly FilesUploadService $filesService,
        private readonly UsersService $usersService,
        private readonly string $profilesFilesDir,
    ) {
    }

    #[AdminRoute('/my-profile', name: 'profile', options: ['methods' => ['GET']])]
    public function profile(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Users) {
            return $this->redirectToRoute('admin');
        }

        $avatar = $this->filesService->getFilesForEntity($user, Files::TYPE_USER_AVATAR)[0] ?? null;
        $passwordForm = $this->createForm(ProfileChangePasswordType::class);

        return $this->render('admin/profile/profile.html.twig', [
            'passwordForm' => $passwordForm->createView(),
            'user' => $user,
            'avatar' => $avatar,
        ]);
    }

    #[AdminRoute('/my-profile/update', name: 'profile_update', options: ['methods' => ['POST', 'GET']])]
    public function updateProfile(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Users) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            $this->usersService->updateProfile($user, $avatarFile, $this->profilesFilesDir);

            $this->addFlash('success', $this->translator->trans('profile.edit.success', [], 'admin'));

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile/edit.html.twig', [
            'profileForm' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[AdminRoute('/my-profile/change-password', name: 'password_change', options: ['methods' => ['POST']])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Users) {
            return $this->redirectToRoute('admin');
        }

        $form = $this->createForm(ProfileChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPlainPassword = $form->get('plainPassword')->getData();

            $this->usersService->changePassword($user, $newPlainPassword);

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            $this->addFlash('success', $this->translator->trans('profile.password_changed', [], 'admin'));

            return $this->redirectToRoute('admin_profile');
        }

        if ($request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json(['success' => false, 'errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('danger', $error->getMessage());
        }

        return $this->redirectToRoute('admin_profile');
    }
}
