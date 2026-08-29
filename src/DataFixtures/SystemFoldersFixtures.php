<?php

namespace App\DataFixtures;

use App\Entity\Folders;
use App\Enum\FolderType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SystemFoldersFixtures extends Fixture implements FixtureGroupInterface
{
    private const array SYSTEM_FOLDERS = [
        ['name' => 'Companii', 'slug' => 'companii', 'mapping' => 'companies_document,companies_logo', 'position' => 1],
        ['name' => 'Utilizatori', 'slug' => 'utilizatori', 'mapping' => 'user_avatar', 'position' => 2],
    ];

    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::SYSTEM_FOLDERS as $def) {
            $folder = new Folders();
            $folder->setName($def['name']);
            $folder->setSlug($def['slug']);
            $folder->setType(FolderType::SYSTEM);
            $folder->setSystemMapping($def['mapping']);
            $folder->setPosition($def['position']);

            $manager->persist($folder);
        }

        $manager->flush();
    }
}
