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

use MoveElevator\Typo3ImageCompression\Configuration;
use MoveElevator\Typo3ImageCompression\Service\{CompressImageServiceInterface, CompressionQuotaAwareInterface};
use TYPO3\CMS\Backend\Backend\Event\SystemInformationToolbarCollectorEvent;
use TYPO3\CMS\Backend\Toolbar\InformationStatus;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SystemInformationToolbar.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class SystemInformationToolbar
{
    public function __construct(
        protected Configuration\ExtensionConfiguration $extensionConfiguration,
        protected CompressImageServiceInterface $compressImageService,
    ) {}

    public function __invoke(SystemInformationToolbarCollectorEvent $systemInformation): void
    {
        if (!$this->extensionConfiguration->isSystemInformationToolbar()) {
            return;
        }

        if ('' === $this->extensionConfiguration->getApiKey()) {
            return;
        }

        if (!$this->compressImageService instanceof CompressionQuotaAwareInterface) {
            return;
        }

        $compressionCount = $this->compressImageService->getCompressionCount();
        if (null === $compressionCount) {
            return;
        }

        $quotaLimit = $this->compressImageService->getQuotaLimit();

        $systemInformation->getToolbarItem()->addSystemInformation(
            $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:label'),
            $this->formatCompressionLimit($compressionCount, $quotaLimit),
            'actions-image',
            GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 13 ? InformationStatus::OK : 'success', // @phpstan-ignore-line TYPO3 v12 uses string, v13+ uses InformationStatus enum
        );
    }

    private function formatCompressionLimit(int $compressionCount, ?int $quotaLimit): string
    {
        if (0 === $compressionCount) {
            return '?';
        }

        if (null === $quotaLimit) {
            return $compressionCount.' / ∞';
        }

        return $compressionCount.' / '.$quotaLimit;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
