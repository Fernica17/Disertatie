<?php

namespace App\Twig;

use App\Service\SettingsService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly SettingsService $settingsService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('setting', $this->getSetting(...)),
        ];
    }

    public function getSetting(string $key): ?string
    {
        return $this->settingsService->get($key);
    }
}
