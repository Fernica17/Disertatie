<?php

namespace App\DataFixtures;

use App\Entity\Cities;
use App\Entity\Counties;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CitiesFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function getDependencies(): array
    {
        return [CountiesFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        if ($manager->getRepository(Cities::class)->count([]) > 0) {
            return;
        }

        $jsonPath = __DIR__ . '/data/cities.json';
        $cities = json_decode(file_get_contents($jsonPath), true);

        // ~16k rows: detach each batch so the unit of work does not grow unbounded
        $batchSize = 500;
        $i = 0;

        foreach ($cities as $data) {
            $city = new Cities();
            $city->setName($data['name']);
            $city->setIsActive($data['is_active']);

            /** @var Counties $county */
            $county = $this->getReference('county-' . $data['old_county_id'], Counties::class);
            $city->setCounty($county);

            $manager->persist($city);

            ++$i;
            if ($i % $batchSize === 0) {
                $manager->flush();
                $manager->clear();
            }
        }

        $manager->flush();
    }
}
