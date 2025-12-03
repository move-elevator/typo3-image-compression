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
    protected array $extConf = [];

    public function __construct()
    {
        $this->extConf = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get(Configuration::EXT_KEY);
    }

    public function getApiKey(): string
    {
        return (string) $this->extConf['apiKey'];
    }

    public function isDebug(): bool
    {
        return (bool) ($this->extConf['debug'] ?? false);
    }

    public function getExcludeFolders(): array
    {
        return GeneralUtility::trimExplode(',', $this->extConf['excludeFolders'] ?? '', true);
    }

    public function getMimeTypes(): array
    {
        return GeneralUtility::trimExplode(',', $this->extConf['mimeTypes'] ?? '', true);
    }

    public function isSystemInformationToolbar(): bool
    {
        return (bool) ($this->extConf['systemInformationToolbar'] ?? false);
    }
}
