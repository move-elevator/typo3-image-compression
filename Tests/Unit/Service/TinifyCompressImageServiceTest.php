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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Service;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use MoveElevator\Typo3ImageCompression\Service\{CompressImageServiceInterface, CompressionQuotaAwareInterface, TinifyCompressImageService};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * TinifyCompressImageServiceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(TinifyCompressImageService::class)]
final class TinifyCompressImageServiceTest extends TestCase
{
    private TinifyCompressImageService $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private PersistenceManager&MockObject $persistenceManagerMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->persistenceManagerMock = $this->createMock(PersistenceManager::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);

        $this->subject = new TinifyCompressImageService(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->persistenceManagerMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
        );
    }

    #[Test]
    public function implementsCompressImageServiceInterface(): void
    {
        self::assertInstanceOf(CompressImageServiceInterface::class, $this->subject);
    }

    #[Test]
    public function implementsCompressionQuotaAwareInterface(): void
    {
        self::assertInstanceOf(CompressionQuotaAwareInterface::class, $this->subject);
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
