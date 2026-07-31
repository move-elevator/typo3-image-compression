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

use MoveElevator\Typo3ImageCompression\Domain\Repository\FileProcessedRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};

/**
 * FileProcessedRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(FileProcessedRepository::class)]
final class FileProcessedRepositoryTest extends \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private FileProcessedRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FileProcessedRepository::class);
    }

    #[Test]
    public function findAllWithErrorsReturnsEmptyArrayWithoutData(): void
    {
        self::assertSame([], $this->subject->findAllWithErrors());
    }

    #[Test]
    public function findAllNonCompressedReturnsEmptyArrayWithoutData(): void
    {
        self::assertSame([], $this->subject->findAllNonCompressed());
    }

    #[Test]
    public function findAllWithErrorsReturnsMatchingRows(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $result = $this->subject->findAllWithErrors();

        self::assertCount(2, $result);
        $uids = array_column($result, 'uid');
        sort($uids);
        self::assertSame([3, 4], $uids);
    }

    #[Test]
    public function findAllWithErrorsRespectsLimit(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $result = $this->subject->findAllWithErrors(1);

        self::assertCount(1, $result);
    }

    #[Test]
    public function findAllNonCompressedReturnsMatchingRows(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $result = $this->subject->findAllNonCompressed();

        self::assertCount(2, $result);
        $uids = array_column($result, 'uid');
        sort($uids);
        self::assertSame([2, 6], $uids);
    }

    #[Test]
    public function findAllNonCompressedRespectsLimit(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $result = $this->subject->findAllNonCompressed(1);

        self::assertCount(1, $result);
    }

    #[Test]
    public function updateCompressStateUpdatesRow(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $this->subject->updateCompressState(2, 1, '');

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file_processedfile')
            ->select('compressed', 'compress_error')
            ->from('sys_file_processedfile')
            ->where('uid = 2')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(1, (int) $row['compressed']);
        self::assertSame('', $row['compress_error']);
    }

    #[Test]
    public function updateCompressStateWithErrorMessageUpdatesRow(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        $this->subject->updateCompressState(2, 0, 'Failed');

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file_processedfile')
            ->select('compressed', 'compress_error')
            ->from('sys_file_processedfile')
            ->where('uid = 2')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(0, (int) $row['compressed']);
        self::assertSame('Failed', $row['compress_error']);
    }

    #[Test]
    public function findStorageIdReturnsStorageForValidProcessedFile(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        self::assertSame(1, $this->subject->findStorageId(1));
    }

    #[Test]
    public function findStorageIdReturnsZeroWhenProcessedFileHasCompressError(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        self::assertSame(0, $this->subject->findStorageId(3));
    }

    #[Test]
    public function findStorageIdReturnsZeroWhenProcessedFileDoesNotExist(): void
    {
        self::assertSame(0, $this->subject->findStorageId(999));
    }

    #[Test]
    public function getCompressionStatisticsReturnsZerosWithoutData(): void
    {
        self::assertSame(
            ['compressed' => 0, 'not_compressed' => 0, 'errors' => 0],
            $this->subject->getCompressionStatistics(),
        );
    }

    #[Test]
    public function getCompressionStatisticsReturnsCounts(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileProcessedRepositoryTest.csv');

        self::assertSame(
            ['compressed' => 1, 'not_compressed' => 2, 'errors' => 2],
            $this->subject->getCompressionStatistics(),
        );
    }
}
