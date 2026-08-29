<?php

namespace App\DataFixtures;

use App\Entity\Countries;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class CountriesFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $jsonPath = __DIR__ . '/data/countries.json';
        $countries = json_decode(file_get_contents($jsonPath), true);

        foreach ($countries as $data) {
            $country = new Countries();
            $country->setName($data['name']);
            $country->setCode($data['code']);
            $country->setIsActive($data['is_active']);
            $country->setIsDefault($data['is_default']);

            $manager->persist($country);

            $this->addReference('country-' . $data['old_id'], $country);
        }

        $manager->flush();
    }
}
