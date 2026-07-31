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
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileStorageRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};

/**
 * FileStorageRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(FileStorageRepository::class)]
final class FileStorageRepositoryTest extends \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private FileStorageRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FileStorageRepository::class);
    }

    #[Test]
    public function findAllReturnsEmptyResultWithoutData(): void
    {
        self::assertCount(0, $this->subject->findAll());
    }

    #[Test]
    public function createQueryDisablesRespectStoragePage(): void
    {
        $query = $this->subject->createQuery();

        self::assertFalse($query->getQuerySettings()->getRespectStoragePage());
    }

    #[Test]
    public function findAllReturnsStoragesRegardlessOfPid(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileStorageRepositoryTest.csv');

        $result = $this->subject->findAll();

        self::assertCount(2, $result);
        foreach ($result as $storage) {
            self::assertInstanceOf(FileStorage::class, $storage);
        }
    }

    #[Test]
    public function queryIgnoresStoragePageIdRestriction(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/FileStorageRepositoryTest.csv');

        $query = $this->subject->createQuery();
        // Restrict to a pid that matches none of the fixture storages (0, 1).
        $query->getQuerySettings()->setStoragePageIds([999]);

        self::assertCount(2, $query->execute());
    }
}
