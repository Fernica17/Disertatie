<?php

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function findByKey(string $key): ?Settings
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    /**
     * @return array<string, Settings>
     */
    public function findAllIndexedByKey(): array
    {
        $settings = $this->findAll();
        $indexed = [];
        foreach ($settings as $setting) {
            $indexed[$setting->getSettingKey()] = $setting;
        }

        return $indexed;
    }

    /**
     * @return Settings[]
     */
    public function findByGroup(string $group): array
    {
        return $this->findBy(['settingGroup' => $group], ['settingKey' => 'ASC']);
    }
}
