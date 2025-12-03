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
use MoveElevator\Typo3ImageCompression\Service\CompressImageService;
use TYPO3\CMS\Backend\Backend\Event\SystemInformationToolbarCollectorEvent;
use TYPO3\CMS\Backend\Toolbar\InformationStatus;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(Configuration::EXT_KEY);
    }

    public function __invoke(SystemInformationToolbarCollectorEvent $systemInformation): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ('' === $this->getApiKey()) {
            return;
        }

        \Tinify\setKey($this->getApiKey());
        \Tinify\validate();

        $systemInformation->getToolbarItem()->addSystemInformation(
            $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:label'),
            $this->getCompressionLimit(),
            'actions-image',
            InformationStatus::OK,
        );
    }

    protected function getApiKey(): string
    {
        return (string) $this->extConf['apiKey'];
    }

    protected function isEnabled(): bool
    {
        return (bool) $this->extConf['systemInformationToolbar'];
    }

    private function getCompressionLimit(): string
    {
        $this->compressImageService->initAction();

        $compressionCount = \Tinify\getCompressionCount();
        if (null === $compressionCount || 0 === $compressionCount) {
            return '?';
        }

        if ($compressionCount <= 500) {
            return $compressionCount.' / 500';
        }

        return $compressionCount.' / ∞';
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
