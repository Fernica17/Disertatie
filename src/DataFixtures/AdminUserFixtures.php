<?php

namespace App\DataFixtures;

use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixtures extends Fixture implements FixtureGroupInterface
{
    public const ADMIN_USER_REFERENCE = 'admin-user';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new Users();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Andrei');
        $admin->setLastName('Popescu');
        $admin->setRoles([Users::ROLE_ADMIN]);
        $admin->setIsActive(true);
        $admin->setIsVerified(true);
        $admin->setIsChangePasswordRequired(false);

        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'AdminP@ssw0rd!');
        $admin->setPassword($hashedPassword);

        $manager->persist($admin);
        $manager->flush();

        $this->addReference(self::ADMIN_USER_REFERENCE, $admin);
    }
}
