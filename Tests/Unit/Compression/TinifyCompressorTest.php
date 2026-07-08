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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface, TinifyCompressor};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * TinifyCompressorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(TinifyCompressor::class)]
final class TinifyCompressorTest extends TestCase
{
    private TinifyCompressor $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;
    private FrontendInterface&MockObject $cacheMock;

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);
        $this->cacheMock = $this->createMock(FrontendInterface::class);

        $this->subject = new TinifyCompressor(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
            $this->cacheMock,
        );
    }

    #[Test]
    public function implementsCompressorInterface(): void
    {
        self::assertInstanceOf(CompressorInterface::class, $this->subject);
    }

    #[Test]
    public function implementsQuotaAwareInterface(): void
    {
        self::assertInstanceOf(QuotaAwareInterface::class, $this->subject);
    }

    #[Test]
    public function getProviderIdentifierReturnsTinify(): void
    {
        self::assertSame('tinify', $this->subject->getProviderIdentifier());
    }

    #[Test]
    public function initActionDoesNothingWhenApiKeyIsEmpty(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getApiKey')
            ->willReturn('');

        // If initAction tries to call Tinify functions, the test would fail
        // because Tinify is not mocked and would throw an error.
        // The expects(self::once()) assertion validates the early return.
        $this->subject->initAction();
    }

    #[Test]
    public function getCompressionCountReturnsCachedValueWithoutCallingApi(): void
    {
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(42);

        // A cache hit must short-circuit before any API interaction; the API
        // key is only read on the (uncached) fetch path.
        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');
        $this->cacheMock->expects(self::never())->method('set');

        self::assertSame(42, $this->subject->getCompressionCount());
    }

    #[Test]
    public function getCompressionCountReturnsNullFromCachedNull(): void
    {
        $this->cacheMock
            ->method('get')
            ->with('compression-count')
            ->willReturn(null);

        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        self::assertNull($this->subject->getCompressionCount());
    }

    #[Test]
    public function getCompressionCountResolvesOnlyOncePerRequest(): void
    {
        // The persistent cache must be read at most once; the second call is
        // served from the per-request memoization.
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(123);

        self::assertSame(123, $this->subject->getCompressionCount());
        self::assertSame(123, $this->subject->getCompressionCount());
    }

    #[Test]
    public function getQuotaLimitReusesCachedCompressionCount(): void
    {
        // getQuotaLimit() delegates to getCompressionCount(); with a cache hit
        // no second lookup and no API call must happen.
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(10);

        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        self::assertSame(500, $this->subject->getQuotaLimit());
    }

    #[Test]
    public function getQuotaLimitReturnsNullForPaidPlanAboveFreeTier(): void
    {
        $this->cacheMock
            ->method('get')
            ->willReturn(750);

        self::assertNull($this->subject->getQuotaLimit());
    }
}
