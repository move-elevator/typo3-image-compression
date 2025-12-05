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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface, TinifyCompressor};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
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

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);

        $this->subject = new TinifyCompressor(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
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
}
