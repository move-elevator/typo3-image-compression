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

namespace MoveElevator\Typo3ImageCompression\EventListener;

use MoveElevator\Typo3ImageCompression\Service\CompressImageService;
use TYPO3\CMS\Backend\Backend\Event\SystemInformationToolbarCollectorEvent;
use TYPO3\CMS\Backend\Toolbar\InformationStatus;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Localization\LanguageService;

#[AsEventListener(identifier: 'typo3-image-compression-system-information-toolbar-event')]
/**
 * SystemInformationToolbar.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class SystemInformationToolbar
{
    protected array $extConf = [];

    public function __construct(protected CompressImageService $compressImageService)
    {
        $this->compressImageService->initAction();
    }

    public function __invoke(SystemInformationToolbarCollectorEvent $systemInformation): void
    {
        $systemInformation->getToolbarItem()->addSystemInformation(
            'tinify',
            $this->getCompressionLimit(),
            'actions-image',
            InformationStatus::OK,
        );
    }

    private function getCompressionLimit(): int
    {
        return \Tinify\compressionCount() ?? 0;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
