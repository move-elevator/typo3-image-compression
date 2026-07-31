<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_image_compression" TYPO3 CMS extension.
 *
 * (c) 2025-2026 Konrad Michalik <km@move-elevator.de>
 * (c) 2025-2026 Ronny Hauptvogel <rh@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Report;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use MoveElevator\Typo3ImageCompression\Report\CompressionStatusProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\{File, FileInterface};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CompressionStatusProviderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionStatusProvider::class)]
final class CompressionStatusProviderTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['reports'];
    protected array $testExtensionsToLoad = ['move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function getLabelReturnsExtensionKey(): void
    {
        $subject = $this->get(CompressionStatusProvider::class);

        self::assertSame('typo3_image_compression', $subject->getLabel());
    }

    #[Test]
    public function getStatusReturnsEmptyArrayWhenStatusReportIsDisabled(): void
    {
        $this->get(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            ['showStatusReport' => false],
        );

        $subject = $this->get(CompressionStatusProvider::class);

        self::assertSame([], $subject->getStatus());
    }

    #[Test]
    public function getStatusReturnsProviderAndStatisticsButNotApiUsageWhenApiKeyIsEmpty(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file_and_processedfile.csv');

        $this->get(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            [
                'showStatusReport' => true,
                'apiKey' => '',
                'mimeTypes' => 'image/avif,image/jpeg,image/png,image/webp',
            ],
        );

        $subject = $this->get(CompressionStatusProvider::class);
        $statuses = $subject->getStatus();

        self::assertArrayHasKey('provider', $statuses);
        self::assertArrayHasKey('statistics', $statuses);
        self::assertArrayNotHasKey('apiUsage', $statuses);

        self::assertSame('2 / 6', $statuses['statistics']->getValue());
        self::assertStringContainsString('Original Files', $statuses['statistics']->getMessage());
    }

    #[Test]
    public function getStatusReportsUnlimitedApiUsageAsOkSeverity(): void
    {
        // CompressorFactory would normally resolve the real (apiKey-empty)
        // TinifyCompressor; a fake QuotaAwareInterface compressor is
        // constructed directly here to drive getApiUsageStatus() with
        // deterministic quota values, none of which are reachable through
        // the real provider without a live TinyPNG account at each tier.
        $subject = $this->createProviderWithQuotaAwareCompressor(250, null);

        $status = $subject->getStatus()['apiUsage'];

        self::assertSame('250 / ∞', $status->getValue());
        self::assertSame(ContextualFeedbackSeverity::OK, $status->getSeverity());
    }

    #[Test]
    public function getStatusReportsWarningSeverityWhenUsageIsAboveSeventyFivePercent(): void
    {
        $subject = $this->createProviderWithQuotaAwareCompressor(400, 500);

        $status = $subject->getStatus()['apiUsage'];

        self::assertSame('400 / 500', $status->getValue());
        self::assertSame(ContextualFeedbackSeverity::WARNING, $status->getSeverity());
    }

    #[Test]
    public function getStatusReportsErrorSeverityWhenUsageIsAboveNinetyPercent(): void
    {
        $subject = $this->createProviderWithQuotaAwareCompressor(475, 500);

        $status = $subject->getStatus()['apiUsage'];

        self::assertSame('475 / 500', $status->getValue());
        self::assertSame(ContextualFeedbackSeverity::ERROR, $status->getSeverity());
    }

    private function createProviderWithQuotaAwareCompressor(
        int $compressionCount,
        ?int $quotaLimit,
    ): CompressionStatusProvider {
        $this->get(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            ['showStatusReport' => true, 'mimeTypes' => 'image/jpeg'],
        );

        return new CompressionStatusProvider(
            $this->get(ExtensionConfiguration::class),
            $this->get(FileRepository::class),
            $this->get(FileProcessedRepository::class),
            new class($compressionCount, $quotaLimit) implements CompressorInterface, QuotaAwareInterface {
                public function __construct(
                    private readonly int $compressionCount,
                    private readonly ?int $quotaLimit,
                ) {}

                public function compress(File|FileInterface $file): void {}

                public function compressProcessedFiles(array $files): void {}

                public function getProviderIdentifier(): string
                {
                    return 'quota-aware-test-double';
                }

                public function getCompressionCount(): ?int
                {
                    return $this->compressionCount;
                }

                public function getQuotaLimit(): ?int
                {
                    return $this->quotaLimit;
                }
            },
        );
    }
}
