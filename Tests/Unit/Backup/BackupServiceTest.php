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

use FilesystemIterator;
use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Resource\{File, ResourceStorage};

/**
 * BackupServiceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(BackupService::class)]
final class BackupServiceTest extends TestCase
{
    private BackupService $subject;

    /**
     * @var string[]
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir().'/index.php',
            'UNIX',
        );

        // GeneralUtility::mkdir_deep() defaults to permission mask 0 (no
        // access at all) when this is unset, which breaks every subsequent
        // directory creation attempt under the same parent for the rest of
        // the test process.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = '0755';

        $this->subject = new BackupService();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
        $this->tmpFiles = [];

        $this->removeBackupDirectory();
    }

    #[Test]
    public function backupReturnsNullWhenSourceFileDoesNotExist(): void
    {
        $fileMock = $this->createFileMock(3);

        self::assertNull($this->subject->backup($fileMock, sys_get_temp_dir().'/does-not-exist-'.bin2hex(random_bytes(8)).'.jpg'));
    }

    #[Test]
    public function backupCopiesFileIntoStorageScopedDirectoryAndReturnsRelativePath(): void
    {
        $sourcePath = $this->createTmpFile('original-bytes');
        $fileMock = $this->createFileMock(5);

        $relativePath = $this->subject->backup($fileMock, $sourcePath);

        self::assertNotNull($relativePath);
        self::assertStringStartsWith('5/', $relativePath);
        self::assertStringEndsWith('.jpg', $relativePath);
        self::assertSame('original-bytes', file_get_contents(sys_get_temp_dir().'/image_compression/backup/'.$relativePath));
    }

    #[Test]
    public function restoreReturnsFalseWhenBackupFileIsMissing(): void
    {
        $targetPath = $this->createTmpFile('current-bytes');

        self::assertFalse($this->subject->restore('does-not-exist/'.bin2hex(random_bytes(8)).'.jpg', $targetPath));
    }

    #[Test]
    public function restoreCopiesBackupContentOntoTargetPath(): void
    {
        $sourcePath = $this->createTmpFile('original-bytes');
        $fileMock = $this->createFileMock(7);
        $relativePath = $this->subject->backup($fileMock, $sourcePath);
        self::assertNotNull($relativePath);

        $targetPath = $this->createTmpFile('compressed-bytes');

        self::assertTrue($this->subject->restore($relativePath, $targetPath));
        self::assertSame('original-bytes', file_get_contents($targetPath));
    }

    #[Test]
    public function pruneReturnsZeroWhenBackupDirectoryDoesNotExist(): void
    {
        self::assertSame(0, $this->subject->prune(30));
    }

    #[Test]
    public function pruneDeletesFilesOlderThanRetentionAndKeepsNewerOnes(): void
    {
        $sourcePath = $this->createTmpFile('original-bytes');
        $oldFileMock = $this->createFileMock(1);
        $newFileMock = $this->createFileMock(2);

        $oldRelativePath = $this->subject->backup($oldFileMock, $sourcePath);
        $newRelativePath = $this->subject->backup($newFileMock, $sourcePath);
        self::assertNotNull($oldRelativePath);
        self::assertNotNull($newRelativePath);

        $oldAbsolutePath = sys_get_temp_dir().'/image_compression/backup/'.$oldRelativePath;
        touch($oldAbsolutePath, time() - (31 * 86400));

        $deleted = $this->subject->prune(30);

        self::assertSame(1, $deleted);
        self::assertFileDoesNotExist($oldAbsolutePath);
        self::assertFileExists(sys_get_temp_dir().'/image_compression/backup/'.$newRelativePath);
    }

    #[Test]
    public function pruneWithDryRunDoesNotDeleteFiles(): void
    {
        $sourcePath = $this->createTmpFile('original-bytes');
        $fileMock = $this->createFileMock(9);
        $relativePath = $this->subject->backup($fileMock, $sourcePath);
        self::assertNotNull($relativePath);

        $absolutePath = sys_get_temp_dir().'/image_compression/backup/'.$relativePath;
        touch($absolutePath, time() - (31 * 86400));

        $deleted = $this->subject->prune(30, true);

        self::assertSame(1, $deleted);
        self::assertFileExists($absolutePath);
    }

    private function createFileMock(int $storageUid): File
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getUid')->willReturn($storageUid);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getStorage')->willReturn($storageMock);

        return $fileMock;
    }

    private function createTmpFile(string $content): string
    {
        $tmpFile = sys_get_temp_dir().'/bs_'.bin2hex(random_bytes(8)).'.jpg';
        file_put_contents($tmpFile, $content);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    private function removeBackupDirectory(): void
    {
        $backupDir = sys_get_temp_dir().'/image_compression';

        if (!is_dir($backupDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $fileInfo->isDir() ? rmdir($fileInfo->getPathname()) : unlink($fileInfo->getPathname());
        }

        rmdir($backupDir);
    }
}
