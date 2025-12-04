<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_image_compression" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 * (c) 2025 Ronny Hauptvogel <rh@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3ImageCompression\Compression;

use TYPO3\CMS\Core\Utility\CommandUtility;

use function array_key_exists;

/**
 * ToolDetection.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class ToolDetection
{
    /**
     * @var array<string, string|string[]>
     */
    private const TOOL_BINARIES = [
        'jpegoptim' => 'jpegoptim',
        'optipng' => 'optipng',
        'pngquant' => 'pngquant',
        'gifsicle' => 'gifsicle',
        'cwebp' => 'cwebp',
        'avifenc' => 'avifenc',
        'imagemagick' => ['magick', 'convert'], // magick (v7+) preferred, convert (v6) as fallback
        'graphicsmagick' => 'gm',
    ];

    /**
     * @var array<string, bool>
     */
    private array $availabilityCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $pathCache = [];

    /**
     * Checks if a specific tool is available on the system.
     */
    public function isAvailable(string $tool): bool
    {
        if (isset($this->availabilityCache[$tool])) {
            return $this->availabilityCache[$tool];
        }

        $binaries = self::TOOL_BINARIES[$tool] ?? null;

        if (null === $binaries) {
            $this->availabilityCache[$tool] = false;

            return false;
        }

        // Support multiple binary names (e.g., magick/convert for ImageMagick)
        $binaries = (array) $binaries;

        foreach ($binaries as $binary) {
            $path = CommandUtility::getCommand($binary);

            if ('' !== $path && false !== $path) {
                $this->availabilityCache[$tool] = true;

                return true;
            }
        }

        $this->availabilityCache[$tool] = false;

        return false;
    }

    /**
     * Returns the full path to a tool's binary, or null if not available.
     */
    public function getToolPath(string $tool): ?string
    {
        if (array_key_exists($tool, $this->pathCache)) {
            return $this->pathCache[$tool];
        }

        $binaries = self::TOOL_BINARIES[$tool] ?? null;

        if (null === $binaries) {
            $this->pathCache[$tool] = null;

            return null;
        }

        // Support multiple binary names (e.g., magick/convert for ImageMagick)
        $binaries = (array) $binaries;

        foreach ($binaries as $binary) {
            $path = CommandUtility::getCommand($binary);

            if ('' !== $path && false !== $path) {
                $this->pathCache[$tool] = (string) $path;

                return $this->pathCache[$tool];
            }
        }

        $this->pathCache[$tool] = null;

        return null;
    }

    /**
     * Checks if any optimized tools (jpegoptim, optipng, pngquant) are available.
     */
    public function hasOptimizedTools(): bool
    {
        return $this->isAvailable('jpegoptim')
            || $this->isAvailable('optipng')
            || $this->isAvailable('pngquant');
    }

    /**
     * Checks if basic tools (ImageMagick or GraphicsMagick) are available.
     */
    public function hasBasicTools(): bool
    {
        return $this->isAvailable('imagemagick')
            || $this->isAvailable('graphicsmagick');
    }

    /**
     * Returns the first available tool from the given list.
     *
     * @param string[] $tools
     */
    public function getFirstAvailable(array $tools): ?string
    {
        foreach ($tools as $tool) {
            if ($this->isAvailable($tool)) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Returns all available tools.
     *
     * @return string[]
     */
    public function getAvailableTools(): array
    {
        $available = [];

        foreach (array_keys(self::TOOL_BINARIES) as $tool) {
            if ($this->isAvailable($tool)) {
                $available[] = $tool;
            }
        }

        return $available;
    }

    /**
     * Returns all supported tool identifiers.
     *
     * @return string[]
     */
    public function getSupportedTools(): array
    {
        return array_keys(self::TOOL_BINARIES);
    }

    /**
     * Clears the internal cache. Useful for testing.
     */
    public function clearCache(): void
    {
        $this->availabilityCache = [];
        $this->pathCache = [];
    }
}
