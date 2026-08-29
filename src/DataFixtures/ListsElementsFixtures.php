<?php

namespace App\DataFixtures;

use App\Entity\Elements;
use App\Entity\Lists;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ListsElementsFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $listsData = [
            [
                'name' => 'Tip Client',
                'slug' => 'tip-client',
                'elements' => [
                    'Laborator',
                    'Producător',
                    'Sănătate',
                    'Distribuitor',
                    'Persoană fizică',
                ],
            ],
            [
                'name' => 'Industrie',
                'slug' => 'industrie',
                'elements' => [
                    'Sănătate',
                    'Farmaceutice',
                    'Cosmetice',
                    'Alimentară',
                    'Băuturi',
                    'Mediu',
                    'Agricultură',
                    'Zootehnie',
                    'Producție',
                    'Cercetare & Dezvoltare',
                ],
            ],
            [
                'name' => 'Dimensiune companie',
                'slug' => 'dimensiune-companie',
                'elements' => [
                    '1-9 angajați',
                    '10-49 angajați',
                    '50-249 angajați',
                    '250+ angajați',
                ],
            ],
        ];

        foreach ($listsData as $listData) {
            $list = new Lists();
            $list->setName($listData['name']);
            $list->setSlug($listData['slug']);

            $manager->persist($list);

            foreach ($listData['elements'] as $elementName) {
                $element = new Elements();
                $element->setName($elementName);
                $element->setIsActive(true);
                $element->setList($list);

                $manager->persist($element);
            }
        }

        $manager->flush();
    }
}
