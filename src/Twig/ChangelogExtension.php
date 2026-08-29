<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the application version, read from the topmost "## [x.y.z]" heading
 * in CHANGELOG.md, to templates.
 */
class ChangelogExtension extends AbstractExtension
{
    private const string CHANGELOG_FILENAME = 'CHANGELOG.md';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('changelog_version', $this->getCurrentVersion(...)),
        ];
    }

    public function getCurrentVersion(): ?string
    {
        $path = $this->projectDir . \DIRECTORY_SEPARATOR . self::CHANGELOG_FILENAME;

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $markdown = file_get_contents($path);

        if ($markdown === false) {
            return null;
        }

        if (preg_match('/^##\s*\[([^\]]+)\]/m', $markdown, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
