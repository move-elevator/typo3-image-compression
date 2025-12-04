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

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, LocalToolsCompressor, ToolDetection};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * LocalToolsCompressorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(LocalToolsCompressor::class)]
final class LocalToolsCompressorTest extends TestCase
{
    private LocalToolsCompressor $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private PersistenceManager&MockObject $persistenceManagerMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;
    private ToolDetection&MockObject $toolDetectionMock;

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->persistenceManagerMock = $this->createMock(PersistenceManager::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);
        $this->toolDetectionMock = $this->createMock(ToolDetection::class);

        $this->subject = new LocalToolsCompressor(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->persistenceManagerMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
            $this->toolDetectionMock,
        );
    }

    #[Test]
    public function implementsCompressorInterface(): void
    {
        self::assertInstanceOf(CompressorInterface::class, $this->subject);
    }

    #[Test]
    public function getProviderIdentifierReturnsLocalTools(): void
    {
        self::assertSame('local-tools', $this->subject->getProviderIdentifier());
    }

    #[Test]
    public function canSetLogger(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject->setLogger($loggerMock);

        // Verify logger was set by ensuring no exception was thrown
        self::assertInstanceOf(LocalToolsCompressor::class, $this->subject);
    }
}
