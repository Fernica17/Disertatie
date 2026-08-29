<?php

namespace App\Service;

use App\Entity\Settings;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SettingsService
{
    private const CACHE_KEY = 'app_settings';
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRepository $settingsRepository,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Default settings definitions with groups and types.
     *
     * @return array<string, array{value: string, group: string, type: string, description: string}>
     */
    public static function getDefaults(): array
    {
        return [
            // Financial
            'default_vat_percent' => [
                'value' => '21.00',
                'group' => Settings::GROUP_FINANCIAL,
                'type' => Settings::TYPE_DECIMAL,
                'description' => 'Procent TVA implicit',
            ],
            'default_currency' => [
                'value' => 'RON',
                'group' => Settings::GROUP_FINANCIAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Moneda implicită',
            ],

            // General
            'app_title' => [
                'value' => 'ERP',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Titlul aplicației (afișat în browser și interfață)',
            ],
            'company_name' => [
                'value' => 'Compania mea',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Numele companiei',
            ],
            'company_cui' => [
                'value' => '',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'CUI companie',
            ],
            'company_reg_com' => [
                'value' => '',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Nr. Reg. Comerțului',
            ],
            'company_address' => [
                'value' => '',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Adresa companiei',
            ],
            'company_phone' => [
                'value' => '',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Telefon companie',
            ],
            'company_email' => [
                'value' => '',
                'group' => Settings::GROUP_GENERAL,
                'type' => Settings::TYPE_STRING,
                'description' => 'Email companie',
            ],
        ];
    }

    /**
     * Get a single setting value, with fallback to default.
     */
    public function get(string $key): ?string
    {
        $all = $this->getAll();

        return $all[$key] ?? $this->getDefaultValue($key);
    }

    /**
     * Get all settings as key => value array (cached).
     *
     * @return array<string, string|null>
     */
    public function getAll(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            $settings = $this->settingsRepository->findAll();
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->getSettingKey()] = $setting->getSettingValue();
            }

            return $result;
        });
    }

    /**
     * Set a single setting value and invalidate cache.
     */
    public function set(string $key, ?string $value): void
    {
        $this->entityManager->beginTransaction();

        try {
            $setting = $this->settingsRepository->findByKey($key);

            if ($setting === null) {
                $setting = new Settings();
                $setting->setSettingKey($key);
                $defaults = self::getDefaults();
                if (isset($defaults[$key])) {
                    $setting->setSettingGroup($defaults[$key]['group']);
                    $setting->setSettingType($defaults[$key]['type']);
                    $setting->setDescription($defaults[$key]['description']);
                }
                $this->entityManager->persist($setting);
            }

            $setting->setSettingValue($value);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->clearCache();

            $this->logger->info('Setting updated', ['key' => $key]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to update setting', ['key' => $key, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Save multiple settings at once.
     *
     * @param array<string, string|null> $values
     */
    public function saveAll(array $values): void
    {
        $this->entityManager->beginTransaction();

        try {
            $existing = $this->settingsRepository->findAllIndexedByKey();
            $defaults = self::getDefaults();

            foreach ($values as $key => $value) {
                if (!isset($defaults[$key])) {
                    continue;
                }

                if (isset($existing[$key])) {
                    $existing[$key]->setSettingValue($value);
                } else {
                    $setting = new Settings();
                    $setting->setSettingKey($key);
                    $setting->setSettingValue($value);
                    $setting->setSettingGroup($defaults[$key]['group']);
                    $setting->setSettingType($defaults[$key]['type']);
                    $setting->setDescription($defaults[$key]['description']);
                    $this->entityManager->persist($setting);
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->clearCache();

            $this->logger->info('Settings saved', ['count' => count($values)]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('Failed to save settings', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    private function getDefaultValue(string $key): ?string
    {
        $defaults = self::getDefaults();

        return $defaults[$key]['value'] ?? null;
    }
}
