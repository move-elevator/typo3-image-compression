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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Domain\Repository;

use MoveElevator\Typo3ImageCompression\Domain\Model\FileStorage;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileRepository, FileStorageRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FileRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(FileRepository::class)]
final class FileRepositoryTest extends \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private FileRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMimeTypes(['image/jpeg']);

        $this->subject = $this->get(FileRepository::class);
    }

    #[Test]
    public function findAllNonCompressedInStorageWithLimitReturnsEmptyResultWithoutData(): void
    {
        $storage = new FileStorage();
        $storage->_setProperty('uid', 1);

        $result = $this->subject->findAllNonCompressedInStorageWithLimit($storage);

        self::assertCount(0, $result);
    }

    #[Test]
    public function findAllNonCompressedInStorageWithLimitReturnsMatchingFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllNonCompressedInStorageWithLimit($storage);

        self::assertCount(2, $result);
    }

    #[Test]
    public function findAllNonCompressedInStorageWithLimitRespectsExcludeFolders(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllNonCompressedInStorageWithLimit($storage, 100, ['/excluded/']);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findAllNonCompressedInStorageWithLimitRespectsLimit(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllNonCompressedInStorageWithLimit($storage, 1);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findAllNonCompressedInStorageWithLimitScopesToStorage(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(2);

        $result = $this->subject->findAllNonCompressedInStorageWithLimit($storage);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findAllWithErrorsInStorageWithLimitReturnsEmptyResultWithoutData(): void
    {
        $storage = new FileStorage();
        $storage->_setProperty('uid', 1);

        $result = $this->subject->findAllWithErrorsInStorageWithLimit($storage);

        self::assertCount(0, $result);
    }

    #[Test]
    public function findAllWithErrorsInStorageWithLimitReturnsMatchingFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllWithErrorsInStorageWithLimit($storage);

        self::assertCount(2, $result);
    }

    #[Test]
    public function findAllWithErrorsInStorageWithLimitRespectsLimit(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllWithErrorsInStorageWithLimit($storage, 1);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findAllWithErrorsInStorageWithLimitRespectsExcludeFolders(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $storage = $this->getStorage(1);

        $result = $this->subject->findAllWithErrorsInStorageWithLimit($storage, 100, ['/image4']);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findCompressionStatusByUidReturnsNullForUnknownFile(): void
    {
        self::assertNull($this->subject->findCompressionStatusByUid(999));
    }

    #[Test]
    public function findCompressionStatusByUidReturnsStatusData(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $status = $this->subject->findCompressionStatusByUid(4);

        self::assertSame(
            ['compressed' => false, 'compress_error' => 'Some error', 'compress_info' => ''],
            $status,
        );
    }

    #[Test]
    public function updateCompressionStatusUpdatesRow(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $this->subject->updateCompressionStatus(1, true, '', 'saved 50%');

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_error', 'compress_info')
            ->from('sys_file')
            ->where('uid = 1')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(1, (int) $row['compressed']);
        self::assertSame('', $row['compress_error']);
        self::assertSame('saved 50%', $row['compress_info']);
    }

    #[Test]
    public function updateCompressionStatusWithErrorUpdatesRow(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        $this->subject->updateCompressionStatus(1, false, 'boom', '');

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_error', 'compress_info')
            ->from('sys_file')
            ->where('uid = 1')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(0, (int) $row['compressed']);
        self::assertSame('boom', $row['compress_error']);
        self::assertSame('', $row['compress_info']);
    }

    #[Test]
    public function getCompressionStatisticsReturnsZerosWithoutData(): void
    {
        self::assertSame(
            ['compressed' => 0, 'not_compressed' => 0, 'errors' => 0],
            $this->subject->getCompressionStatistics(['image/jpeg']),
        );
    }

    #[Test]
    public function getCompressionStatisticsReturnsCounts(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileRepositoryTest.csv');

        self::assertSame(
            ['compressed' => 1, 'not_compressed' => 3, 'errors' => 2],
            $this->subject->getCompressionStatistics(['image/jpeg']),
        );
    }

    private function getStorage(int $uid): FileStorage
    {
        $storage = $this->get(FileStorageRepository::class)->findByUid($uid);
        self::assertInstanceOf(FileStorage::class, $storage);

        return $storage;
    }

    /**
     * @param string[] $mimeTypes
     */
    private function configureMimeTypes(array $mimeTypes): void
    {
        $coreExtensionConfigurationMock = $this->createMock(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class);
        $coreExtensionConfigurationMock->method('get')->willReturn([
            'mimeTypes' => implode(',', $mimeTypes),
        ]);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class, $coreExtensionConfigurationMock);
    }
}
