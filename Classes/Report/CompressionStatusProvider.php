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

namespace MoveElevator\Typo3ImageCompression\Report;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface};
use MoveElevator\Typo3ImageCompression\Configuration;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use MoveElevator\Typo3ImageCompression\Utility\ViewUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\{Status, StatusProviderInterface};

use function sprintf;

/**
 * CompressionStatusProvider.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class CompressionStatusProvider implements StatusProviderInterface
{
    private const COLOR_SUCCESS = 'var(--typo3-state-success-border-color)';
    private const COLOR_WARNING = 'var(--typo3-state-warning-border-color)';
    private const COLOR_DANGER = 'var(--typo3-state-danger-border-color)';

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly FileRepository $fileRepository,
        private readonly FileProcessedRepository $fileProcessedRepository,
        private readonly CompressorInterface $compressor,
    ) {}

    public function getLabel(): string
    {
        return 'typo3_image_compression';
    }

    /**
     * @return Status[]
     */
    public function getStatus(): array
    {
        if (!$this->extensionConfiguration->isShowStatusReport()) {
            return [];
        }

        $statuses = [];

        $statuses['provider'] = $this->getProviderStatus();
        $statuses['statistics'] = $this->getStatisticsStatus();

        if ($this->compressor instanceof QuotaAwareInterface) {
            $apiUsageStatus = $this->getApiUsageStatus();
            if (null !== $apiUsageStatus) {
                $statuses['apiUsage'] = $apiUsageStatus;
            }
        }

        return $statuses;
    }

    private function getProviderStatus(): Status
    {
        $provider = $this->extensionConfiguration->getProvider();

        return new Status(
            $this->translate('report.provider'),
            $provider,
            $this->translate('report.provider.description'),
            ContextualFeedbackSeverity::INFO,
        );
    }

    private function getStatisticsStatus(): Status
    {
        $originalStats = $this->fileRepository->getCompressionStatistics(
            $this->extensionConfiguration->getMimeTypes(),
        );
        $processedStats = $this->fileProcessedRepository->getCompressionStatistics();

        $originalTotal = $originalStats['compressed'] + $originalStats['not_compressed'] + $originalStats['errors'];
        $originalPercent = $originalTotal > 0 ? (int) round(($originalStats['compressed'] / $originalTotal) * 100) : 0;

        $processedTotal = $processedStats['compressed'] + $processedStats['not_compressed'] + $processedStats['errors'];
        $processedPercent = $processedTotal > 0 ? (int) round(($processedStats['compressed'] / $processedTotal) * 100) : 0;

        $hasErrors = $originalStats['errors'] > 0 || $processedStats['errors'] > 0;

        $totalCompressed = $originalStats['compressed'] + $processedStats['compressed'];
        $totalFiles = $originalTotal + $processedTotal;
        $value = sprintf('%d / %d', $totalCompressed, $totalFiles);
        $severity = $hasErrors ? ContextualFeedbackSeverity::WARNING : ContextualFeedbackSeverity::OK;

        $message = ViewUtility::renderTemplate('CompressionStatistics', 'Report/', [
            'original' => [
                'statistics' => $originalStats,
                'total' => $originalTotal,
                'percent' => $originalPercent,
                'color' => $originalStats['errors'] > 0 ? self::COLOR_WARNING : self::COLOR_SUCCESS,
            ],
            'processed' => [
                'statistics' => $processedStats,
                'total' => $processedTotal,
                'percent' => $processedPercent,
                'color' => $processedStats['errors'] > 0 ? self::COLOR_WARNING : self::COLOR_SUCCESS,
            ],
            'hasErrors' => $hasErrors,
            'labels' => [
                'original' => $this->translate('report.original'),
                'processed' => $this->translate('report.processed'),
                'compressed' => $this->translate('report.compressed'),
                'notCompressed' => $this->translate('report.not_compressed'),
                'errors' => $this->translate('report.errors'),
                'errorsMessage' => $this->translate('report.errors.message'),
            ],
        ]);

        return new Status(
            $this->translate('report.statistics'),
            $value,
            $message,
            $severity,
        );
    }

    private function getApiUsageStatus(): ?Status
    {
        if (!$this->compressor instanceof QuotaAwareInterface) {
            return null;
        }

        $compressionCount = $this->compressor->getCompressionCount();
        if (null === $compressionCount) {
            return null;
        }

        $quotaLimit = $this->compressor->getQuotaLimit();

        if (null === $quotaLimit) {
            $value = $compressionCount.' / ∞';
            $severity = ContextualFeedbackSeverity::OK;
            $message = $this->translate('report.api_usage.description');
        } else {
            $value = $compressionCount.' / '.$quotaLimit;
            $usagePercent = (int) round(($compressionCount / $quotaLimit) * 100);

            if ($usagePercent >= 90) {
                $severity = ContextualFeedbackSeverity::ERROR;
                $color = self::COLOR_DANGER;
            } elseif ($usagePercent >= 75) {
                $severity = ContextualFeedbackSeverity::WARNING;
                $color = self::COLOR_WARNING;
            } else {
                $severity = ContextualFeedbackSeverity::OK;
                $color = self::COLOR_SUCCESS;
            }

            $message = ViewUtility::renderTemplate('ApiUsage', 'Report/', [
                'hasQuotaLimit' => true,
                'percent' => $usagePercent,
                'color' => $color,
                'description' => $this->translate('report.api_usage.description'),
            ]);
        }

        return new Status(
            $this->translate('report.api_usage'),
            $value,
            $message,
            $severity,
        );
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:'.$key,
        );
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
