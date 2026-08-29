<?php

namespace App\Entity;

use App\Repository\SettingsRepository;
use App\Traits\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_SETTING_KEY', columns: ['setting_key'])]
class Settings
{
    use TimestampTrait;

    public const GROUP_GENERAL = 'general';
    public const GROUP_FINANCIAL = 'financial';
    public const GROUP_SERIES = 'series';
    public const GROUP_NOTIFICATIONS = 'notifications';
    public const GROUP_FILES = 'files';
    public const GROUP_DISPLAY = 'display';

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_BOOLEAN = 'boolean';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $settingKey = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $settingValue = null;

    #[ORM\Column(length: 50)]
    private string $settingGroup = self::GROUP_GENERAL;

    #[ORM\Column(length: 20)]
    private string $settingType = self::TYPE_STRING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): ?string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;

        return $this;
    }

    public function getSettingValue(): ?string
    {
        return $this->settingValue;
    }

    public function setSettingValue(?string $settingValue): static
    {
        $this->settingValue = $settingValue;

        return $this;
    }

    public function getSettingGroup(): string
    {
        return $this->settingGroup;
    }

    public function setSettingGroup(string $settingGroup): static
    {
        $this->settingGroup = $settingGroup;

        return $this;
    }

    public function getSettingType(): string
    {
        return $this->settingType;
    }

    public function setSettingType(string $settingType): static
    {
        $this->settingType = $settingType;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function __toString(): string
    {
        return $this->settingKey ?? '';
    }
}
