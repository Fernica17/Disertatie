<?php

namespace App\DataFixtures;

use App\Entity\Companies;
use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DevUsersFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const MANAGER_USER_REFERENCE = 'manager-user';
    public const CLIENT_USER_REFERENCE = 'client-user';

    private const MASS_COUNT = 10000;
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [
            DevCompaniesFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $managerUser = new Users();
        $managerUser->setEmail('manager@example.com');
        $managerUser->setFirstName('Elena');
        $managerUser->setLastName('Ionescu');
        $managerUser->setRoles([Users::ROLE_MANAGER]);
        $managerUser->setIsActive(true);
        $managerUser->setIsVerified(true);
        $managerUser->setIsChangePasswordRequired(false);
        $managerUser->setPassword($this->passwordHasher->hashPassword($managerUser, 'ManagerP@ssw0rd!'));
        $manager->persist($managerUser);
        $this->addReference(self::MANAGER_USER_REFERENCE, $managerUser);

        $secondManager = new Users();
        $secondManager->setEmail('manager2@example.com');
        $secondManager->setFirstName('Mihai');
        $secondManager->setLastName('Dragomir');
        $secondManager->setRoles([Users::ROLE_MANAGER]);
        $secondManager->setIsActive(true);
        $secondManager->setIsVerified(true);
        $secondManager->setIsChangePasswordRequired(false);
        $secondManager->setPassword($this->passwordHasher->hashPassword($secondManager, 'UserP@ssw0rd!'));
        $manager->persist($secondManager);

        $clientCompany = $this->getReference(DevCompaniesFixtures::CLIENT_COMPANY_REFERENCE, Companies::class);
        $clientUser = new Users();
        $clientUser->setEmail('client@example.com');
        $clientUser->setFirstName('Alexandru');
        $clientUser->setLastName('Popescu');
        $clientUser->setRoles([Users::ROLE_CLIENT]);
        $clientUser->setIsActive(true);
        $clientUser->setIsVerified(true);
        $clientUser->setIsChangePasswordRequired(false);
        $clientUser->setPassword($this->passwordHasher->hashPassword($clientUser, 'ClientP@ssw0rd!'));
        $clientUser->setCompany($clientCompany);
        $manager->persist($clientUser);
        $this->addReference(self::CLIENT_USER_REFERENCE, $clientUser);

        $manager->flush();

        // ==================== MASS DATA ====================
        if (!getenv('FIXTURES_MASS')) {
            return;
        }

        $faker = Factory::create('ro_RO');
        $roles = [Users::ROLE_ADMIN, Users::ROLE_MANAGER];

        for ($i = 1; $i <= self::MASS_COUNT; ++$i) {
            $user = new Users();
            $user->setEmail("mass-user-{$i}@example.com");
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setRoles([$faker->randomElement($roles)]);
            $user->setPassword('$2y$13$mass-fixture-dummy-hash');
            $user->setIsActive($faker->boolean(90));
            $user->setIsVerified($faker->boolean(85));
            $user->setIsChangePasswordRequired(false);
            $user->setPhone($faker->phoneNumber());
            $manager->persist($user);

            if ($i % self::BATCH_SIZE === 0) {
                $manager->flush();
                $manager->clear();
            }
        }
        $manager->flush();
    }
}
