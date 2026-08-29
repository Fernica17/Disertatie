<?php

namespace App\DataFixtures;

use App\Entity\Settings;
use App\Service\SettingsService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class SettingsFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['prod', 'dev'];
    }

    public function load(ObjectManager $manager): void
    {
        $defaults = SettingsService::getDefaults();

        foreach ($defaults as $key => $def) {
            $setting = new Settings();
            $setting->setSettingKey($key);
            $setting->setSettingValue($def['value']);
            $setting->setSettingGroup($def['group']);
            $setting->setSettingType($def['type']);
            $setting->setDescription($def['description']);

            $manager->persist($setting);
        }

        $manager->flush();
    }
}
