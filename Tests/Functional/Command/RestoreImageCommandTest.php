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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Command;

use MoveElevator\Typo3ImageCompression\Command\RestoreImageCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;
use function strlen;

/**
 * RestoreImageCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreImageCommand::class)]
final class RestoreImageCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester($this->get(RestoreImageCommand::class));
    }

    #[Test]
    public function executeRestoresFileFromBackupAndResetsCompressionState(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $backupRelativePath = $storageUid.'/backup-hash.jpg';
        $this->writeBackupFile($backupRelativePath, 'original-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', $backupRelativePath);

        $this->commandTester->execute(['uid' => $fileUid]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Restored file '.$fileUid, $this->commandTester->getDisplay());
        self::assertStringContainsString('Restored: 1, Failed: 0', $this->commandTester->getDisplay());
        self::assertSame('original-bytes', file_get_contents(Environment::getPublicPath().'/fileadmin/test/photo.jpg'));

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_error', 'backup_path')
            ->from('sys_file')
            ->where('uid = '.$fileUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(0, (int) $row['compressed']);
        self::assertSame('', (string) $row['compress_error']);
        self::assertSame('', (string) $row['backup_path']);
    }

    #[Test]
    public function executeReindexesTheFalRecordSoSizeAndHashReflectTheRestoredContent(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $backupRelativePath = $storageUid.'/backup-hash.jpg';
        $this->writeBackupFile($backupRelativePath, 'a-different-length-original');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', $backupRelativePath);

        $this->commandTester->execute(['uid' => $fileUid]);

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('size', 'sha1')
            ->from('sys_file')
            ->where('uid = '.$fileUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(strlen('a-different-length-original'), (int) $row['size']);
        self::assertSame(sha1('a-different-length-original'), (string) $row['sha1']);
    }

    #[Test]
    public function executeWithAllRestoresEveryBackedUpFile(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('a.jpg', 'compressed-a');
        $this->writeRealFile('b.jpg', 'compressed-b');
        $this->writeBackupFile($storageUid.'/a-hash.jpg', 'original-a');
        $this->writeBackupFile($storageUid.'/b-hash.jpg', 'original-b');
        $this->importSysFileRow($storageUid, '/a.jpg', 'a.jpg', $storageUid.'/a-hash.jpg');
        $this->importSysFileRow($storageUid, '/b.jpg', 'b.jpg', $storageUid.'/b-hash.jpg');

        $this->commandTester->execute(['--all' => true]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Restored: 2, Failed: 0', $this->commandTester->getDisplay());
        self::assertSame('original-a', file_get_contents(Environment::getPublicPath().'/fileadmin/test/a.jpg'));
        self::assertSame('original-b', file_get_contents(Environment::getPublicPath().'/fileadmin/test/b.jpg'));
    }

    private function createLocalTestStorage(): int
    {
        GeneralUtility::mkdir_deep(Environment::getPublicPath().'/fileadmin/test/');

        return $this->get(StorageRepository::class)->createLocalStorage(
            'Test storage',
            'fileadmin/test/',
            'relative',
        );
    }

    private function writeRealFile(string $fileName, string $contents): void
    {
        GeneralUtility::writeFile(Environment::getPublicPath().'/fileadmin/test/'.$fileName, $contents);
    }

    private function writeBackupFile(string $relativePath, string $contents): void
    {
        $absolutePath = Environment::getVarPath().'/image_compression/backup/'.$relativePath;
        GeneralUtility::mkdir_deep(dirname($absolutePath));
        GeneralUtility::writeFile($absolutePath, $contents);
    }

    private function importSysFileRow(int $storageUid, string $identifier, string $name, string $backupPath): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file');
        $connection->insert('sys_file', [
            'pid' => 0,
            'storage' => $storageUid,
            'identifier' => $identifier,
            'identifier_hash' => sha1($identifier),
            'folder_hash' => sha1(dirname($identifier)),
            'name' => $name,
            'mime_type' => 'image/jpeg',
            'missing' => 0,
            'compressed' => 1,
            'backup_path' => $backupPath,
        ]);

        return (int) $connection->lastInsertId('sys_file');
    }
}
