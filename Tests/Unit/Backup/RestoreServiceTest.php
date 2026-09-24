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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Backup;

use MoveElevator\Typo3ImageCompression\Backup\{BackupService, RestoreService};
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * RestoreServiceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreService::class)]
final class RestoreServiceTest extends TestCase
{
    private FileRepository&MockObject $fileRepositoryMock;
    private ResourceFactory $resourceFactory;
    private BackupService&MockObject $backupServiceMock;
    private RestoreService $subject;

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        // ResourceFactory cannot be doubled by PHPUnit; the "no backup" path
        // tested here never reaches it, so an instance created without
        // invoking the constructor is sufficient to satisfy the type.
        $this->resourceFactory = (new ReflectionClass(ResourceFactory::class))->newInstanceWithoutConstructor();
        $this->backupServiceMock = $this->createMock(BackupService::class);

        $this->subject = new RestoreService($this->fileRepositoryMock, $this->resourceFactory, $this->backupServiceMock);
    }

    #[Test]
    public function restoreByUidReturnsFalseWhenNoBackupIsSet(): void
    {
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(7)->willReturn(null);
        $this->backupServiceMock->expects(self::never())->method('restore');

        self::assertFalse($this->subject->restoreByUid(7));
    }
}
