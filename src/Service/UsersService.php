<?php

namespace App\Service;

use App\Dto\User\CreateUserDto;
use App\Dto\User\UpdateUserDto;
use App\Entity\Files;
use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Security\EmailVerifier;
use App\Service\Audit\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UsersService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsersRepository $usersRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger,
        private readonly EmailVerifier $emailVerifier,
        private readonly FilesUploadService $filesUploadService,
        private readonly AuditService $auditService,
        private readonly FaceRecognitionService $faceRecognition,
        private readonly string $profilesFilesDir,
    ) {
    }

    /**
     * Erases the reference photo and the face embedding derived from it.
     *
     * Both sides go together: leaving the embedding behind would keep face
     * login working for a user whose photo the admin just deleted.
     */
    public function removeReferencePhoto(Users $user): void
    {
        foreach ($this->filesUploadService->getFilesForEntity($user, Files::TYPE_USER_AVATAR) as $file) {
            $this->filesUploadService->remove($file);
        }

        $this->entityManager->flush();
        $this->faceRecognition->deleteEnrollment($user);

        $this->logger->info('Reference photo removed', ['user_id' => $user->getId()]);
    }

    /**
     * Stores the reference photo and registers it with the face service.
     *
     * Runs after the user is committed, because enrolment needs the user id and
     * must not be able to roll back a save. A photo the recogniser rejects still
     * becomes the avatar; only face login is unavailable, and the caller gets a
     * translation key back so it can say why.
     *
     * @return ?string translation key describing the problem, null on success
     */
    public function storeReferencePhoto(Users $user, UploadedFile $photo): ?string
    {
        foreach ($this->filesUploadService->getFilesForEntity($user, Files::TYPE_USER_AVATAR) as $old) {
            $this->filesUploadService->remove($old);
        }

        // upload() moves the temporary file, so enrol from the stored copy
        $stored = $this->filesUploadService->upload(
            $photo,
            $user,
            $this->profilesFilesDir,
            Files::TYPE_USER_AVATAR,
        );

        $this->entityManager->flush();

        $path = $this->filesUploadService->absolutePath($stored);

        if ($path === null || !is_file($path)) {
            $this->logger->error('Stored photo is missing on disk', ['user_id' => $user->getId()]);

            return 'users.face.error.bad_image';
        }

        $result = $this->faceRecognition->enroll($user, new File($path));

        if (!$result['ok']) {
            $this->logger->warning('Face enrolment failed', [
                'user_id' => $user->getId(),
                'reason' => $result['reason'],
            ]);
        }

        return $result['ok'] ? null : $result['reason'];
    }

    /**
     * Create a new user
     * ALL business logic stays in the service!
     */
    public function create(CreateUserDto $dto): Users
    {
        $this->entityManager->beginTransaction();

        try {
            $user = new Users();
            $user->setEmail($dto->email);
            $user->setFirstName($dto->firstName);
            $user->setLastName($dto->lastName);

            $user->setPlainPassword($dto->plainPassword);
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->plainPassword);
            $user->setPassword($hashedPassword);

            if ($dto->role) {
                $user->setRoles([$dto->role]);
            }

            $user->setIsActive($dto->isActive);
            $user->setCompany($dto->company);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('User created', [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user);

            return $user;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'email' => $dto->email,
            ]);

            throw $e;
        }
    }

    /**
     * Update existing user.
     */
    public function update(Users $user, UpdateUserDto $dto): Users
    {
        $this->entityManager->beginTransaction();

        try {
            $user->setEmail($dto->email);
            $user->setFirstName($dto->firstName);
            $user->setLastName($dto->lastName);

            if ($dto->plainPassword !== null) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->plainPassword);
                $user->setPassword($hashedPassword);
            }

            if ($dto->role !== null) {
                $user->setRoles([$dto->role]);
            }

            $user->setIsActive($dto->isActive);
            $user->setCompany($dto->company);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('User updated', [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            return $user;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to update user', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete user.
     */
    public function delete(Users $user): void
    {
        $this->entityManager->beginTransaction();

        try {
            $userId = $user->getId();
            $userEmail = $user->getEmail();

            // Biometric data must go with the account (GDPR Art. 17). Done before
            // the delete so the id is still available to address the face service.
            $this->faceRecognition->deleteEnrollment($user);

            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->entityManager->commit();

            // GDPR: delete audit data from separate audit DB (after main commit)
            $this->auditService->deleteUserAuditData($userId);

            $this->logger->info('User deleted', [
                'id' => $userId,
                'email' => $userEmail,
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to delete user', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Find user by ID.
     */
    public function findById(int $id): ?Users
    {
        return $this->usersRepository->find($id);
    }

    /**
     * Find all active users.
     */
    public function findAllActive(): array
    {
        return $this->usersRepository->findBy(['isActive' => true]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Users $user, ?UploadedFile $avatarFile = null, ?string $profilesFilesDir = null): Users
    {
        $this->entityManager->beginTransaction();

        try {
            if ($avatarFile && $profilesFilesDir) {
                $oldAvatars = $this->filesUploadService->getFilesForEntity($user, Files::TYPE_USER_AVATAR);
                foreach ($oldAvatars as $oldAvatar) {
                    $this->filesUploadService->remove($oldAvatar);
                }
                $this->filesUploadService->upload(
                    $avatarFile,
                    $user,
                    $profilesFilesDir,
                    Files::TYPE_USER_AVATAR,
                );
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('User profile updated', [
                'user_id' => $user->getId(),
            ]);

            return $user;
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to update user profile', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }

    /**
     * Change user password.
     */
    public function changePassword(Users $user, string $newPassword, bool $clearChangeRequired = true): void
    {
        $this->entityManager->beginTransaction();

        try {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            if ($clearChangeRequired) {
                $user->setIsChangePasswordRequired(false);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Password changed', [
                'user_id' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to change password', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw $e;
        }
    }
}
