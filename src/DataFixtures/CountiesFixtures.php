<?php

namespace App\DataFixtures;

use App\Entity\Counties;
use App\Entity\Countries;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CountiesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function getDependencies(): array
    {
        return [CountriesFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $jsonPath = __DIR__ . '/data/counties.json';
        $counties = json_decode(file_get_contents($jsonPath), true);

        if ($manager->getRepository(Counties::class)->count([]) > 0) {
            foreach ($counties as $data) {
                $existing = $manager->getRepository(Counties::class)->findOneBy(['code' => $data['code']]);

                if ($existing !== null) {
                    $this->addReference('county-' . $data['old_id'], $existing);
                }
            }

            return;
        }

        foreach ($counties as $data) {
            $county = new Counties();
            $county->setName($data['name']);
            $county->setCode($data['code']);
            $county->setIsActive($data['is_active']);

            /** @var Countries $country */
            $country = $this->getReference('country-' . $data['old_country_id'], Countries::class);
            $county->setCountry($country);

            $manager->persist($county);

            $this->addReference('county-' . $data['old_id'], $county);
        }

        $manager->flush();
    }
}
