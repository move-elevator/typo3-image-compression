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

namespace MoveElevator\Typo3ImageCompression\Configuration;

use MoveElevator\Typo3ImageCompression\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ExtensionConfiguration.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class ExtensionConfiguration
{
    public const PROVIDER_TINIFY = 'tinify';
    public const PROVIDER_LOCAL_TOOLS = 'local-tools';
    public const PROVIDER_LOCAL_BASIC = 'local-basic';

    /**
     * @var array<string, mixed>
     */
    protected array $extConf = [];

    public function __construct()
    {
        $this->extConf = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get(Configuration::EXT_KEY);
    }

    /**
     * Returns the configured compression provider.
     */
    public function getProvider(): string
    {
        return (string) ($this->extConf['provider'] ?? self::PROVIDER_TINIFY);
    }

    public function getApiKey(): string
    {
        return (string) ($this->extConf['apiKey'] ?? '');
    }

    public function isDebug(): bool
    {
        return (bool) ($this->extConf['debug'] ?? false);
    }

    /**
     * @return string[]
     */
    public function getExcludeFolders(): array
    {
        return GeneralUtility::trimExplode(',', (string) ($this->extConf['excludeFolders'] ?? ''), true);
    }

    /**
     * @return string[]
     */
    public function getMimeTypes(): array
    {
        return GeneralUtility::trimExplode(',', (string) ($this->extConf['mimeTypes'] ?? ''), true);
    }

    public function isSystemInformationToolbar(): bool
    {
        return (bool) ($this->extConf['systemInformationToolbar'] ?? false);
    }

    public function isShowCompressionStatus(): bool
    {
        return (bool) ($this->extConf['showCompressionStatus'] ?? true);
    }

    public function isShowStatusReport(): bool
    {
        return (bool) ($this->extConf['showStatusReport'] ?? true);
    }

    /**
     * Returns the JPEG quality setting for local compression (1-100).
     */
    public function getJpegQuality(): int
    {
        $quality = (int) ($this->extConf['jpegQuality'] ?? 85);

        return max(1, min(100, $quality));
    }

    /**
     * Returns the PNG quality setting for local compression (1-100).
     */
    public function getPngQuality(): int
    {
        $quality = (int) ($this->extConf['pngQuality'] ?? 85);

        return max(1, min(100, $quality));
    }

    /**
     * Returns the WebP quality setting for local compression (1-100).
     */
    public function getWebpQuality(): int
    {
        $quality = (int) ($this->extConf['webpQuality'] ?? 80);

        return max(1, min(100, $quality));
    }
}
