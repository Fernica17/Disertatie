<?php

namespace App\DataFixtures;

use App\Entity\Cities;
use App\Entity\Companies;
use App\Entity\Countries;
use App\Enum\CompanyStatus;
use App\Enum\PersonType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class DevCompaniesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public const CLIENT_COMPANY_REFERENCE = 'client-company';

    private const MASS_COUNT = 10000;
    private const BATCH_SIZE = 500;

    /**
     * A small, readable demo set so a fresh dev database is not empty.
     * Bulk data stays behind FIXTURES_MASS.
     */
    private const DEMO_COMPANIES = [
        [
            'name' => 'MediCare Diagnostics SRL',
            'fiscalCode' => 'RO12345678',
            'status' => CompanyStatus::ACTIVE,
            'city' => 'Sector 1',
            'address' => 'Str. Amurgului nr. 12',
            'email' => 'contact@medicare-diagnostics.test',
            'phone' => '+40 21 300 1000',
            'reference' => self::CLIENT_COMPANY_REFERENCE,
        ],
        [
            'name' => 'Nordic Lab Solutions SRL',
            'fiscalCode' => 'RO23456789',
            'status' => CompanyStatus::ACTIVE,
            'city' => 'Cluj-Napoca',
            'address' => 'Bd. Eroilor nr. 4',
            'email' => 'office@nordiclab.test',
            'phone' => '+40 264 400 200',
        ],
        [
            'name' => 'Aqua Test Services SA',
            'fiscalCode' => 'RO34567890',
            'status' => CompanyStatus::PROSPECT,
            'city' => 'Timisoara',
            'address' => 'Str. Torontalului nr. 45',
            'email' => 'info@aquatest.test',
            'phone' => '+40 256 500 300',
        ],
        [
            'name' => 'ChemSupply Distribution SRL',
            'fiscalCode' => 'RO45678901',
            'status' => CompanyStatus::ACTIVE,
            'city' => 'Brasov',
            'address' => 'Str. Zizinului nr. 108',
            'email' => 'vanzari@chemsupply.test',
            'phone' => '+40 268 600 400',
        ],
        [
            'name' => 'TehnoService Instal SRL',
            'fiscalCode' => 'RO56789012',
            'status' => CompanyStatus::ACTIVE,
            'city' => 'Iasi',
            'address' => 'Sos. Nationala nr. 21',
            'email' => 'contact@tehnoservice.test',
            'phone' => '+40 232 700 500',
        ],
        [
            'name' => 'Popescu Ion PFA',
            'fiscalCode' => 'RO67890123',
            'status' => CompanyStatus::SUSPENDED,
            'personType' => PersonType::PHYSICAL,
            'city' => 'Constanta',
            'address' => 'Str. Mircea cel Batran nr. 7',
            'email' => 'ion.popescu@pfa.test',
            'phone' => '+40 241 800 600',
        ],
    ];

    public static function getGroups(): array
    {
        return ['dev'];
    }

    public function getDependencies(): array
    {
        return [CitiesFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $romania = $manager->getRepository(Countries::class)->findOneBy(['code' => 'RO']);

        foreach (self::DEMO_COMPANIES as $definition) {
            $company = new Companies();
            $company->setName($definition['name']);
            $company->setFiscalCode($definition['fiscalCode']);
            $company->setStatus($definition['status']);
            $company->setPersonType($definition['personType'] ?? PersonType::LEGAL);
            $company->setAddress($definition['address']);
            $company->setEmail($definition['email']);
            $company->setPhone($definition['phone']);
            $company->setCountry($romania);

            $city = $manager->getRepository(Cities::class)->findOneBy(['name' => $definition['city']]);
            if ($city !== null) {
                $company->setCity($city);
            }

            $manager->persist($company);

            if (isset($definition['reference'])) {
                $this->addReference($definition['reference'], $company);
            }
        }

        $manager->flush();

        // ==================== MASS DATA ====================
        if (!getenv('FIXTURES_MASS')) {
            return;
        }

        $faker = Factory::create('ro_RO');
        $companyStatuses = CompanyStatus::cases();
        $personTypes = PersonType::cases();

        for ($i = 1; $i <= self::MASS_COUNT; ++$i) {
            $company = new Companies();
            $company->setName($faker->company() . " #{$i}");
            $company->setFiscalCode('RO' . $faker->unique()->numerify('########'));
            $company->setPersonType($faker->randomElement($personTypes));
            $company->setPhone($faker->phoneNumber());
            $company->setEmail("company-{$i}@" . $faker->domainName());
            $company->setStatus($faker->randomElement($companyStatuses));
            $company->setAddress($faker->address());
            $manager->persist($company);

            if ($i % self::BATCH_SIZE === 0) {
                $manager->flush();
                $manager->clear();
            }
        }
        $manager->flush();
    }
}
