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

use MoveElevator\Typo3ImageCompression\Command\CompressImageCommand;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * CompressImageCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressImageCommand::class)]
final class CompressImageCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commandTester = new CommandTester($this->get(CompressImageCommand::class));
    }

    #[Test]
    public function executeOutputsNoFilesMessageWhenNothingToCompress(): void
    {
        $this->commandTester->execute([]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('No files to compress.', $this->commandTester->getDisplay());
    }

    #[Test]
    public function executeAbortsTheRunWithoutRecordingAnErrorWhenApiKeyIsMissing(): void
    {
        // No API key is configured (the extension default is empty), so the
        // real TinyPNG call fails with a Tinify\AccountException. That is a
        // run-wide problem (GH-50), not a per-file one: TinifyCompressor
        // rethrows it as CompressionAbortedException, the command stops
        // attempting further files, and no compress_error is written to the
        // sys_file row (the next scheduled run will simply try again).
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile($storageUid, 'photo.jpg', 'not-a-real-jpeg-but-nonempty-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', 'image/jpeg');

        $this->commandTester->execute(['limit' => 10]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Compression run aborted', $display);

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_error')
            ->from('sys_file')
            ->where('uid = '.$fileUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(0, (int) $row['compressed']);
        self::assertSame('', (string) $row['compress_error']);
    }

    #[Test]
    public function executeWithIncludeProcessedOptionProcessesProcessedFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/ProcessedFiles.csv');

        $this->commandTester->execute(['--include-processed' => true]);

        // No API key is configured, so the (real) TinifyCompressor fails for
        // this processed file too. Before this outcome was read back from the
        // row instead of assumed, this asserted "1/1 compressed" here, which
        // was wrong: compressProcessedFiles() never actually compressed it.
        self::assertSame(1, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Processed files: 0/1 compressed, 0 skipped, 1 errors', $display);
        self::assertStringContainsString('Compression Summary', $display);
    }

    #[Test]
    public function executeWithRetryErrorsOptionRetriesPreviouslyFailedProcessedFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/ProcessedFiles.csv');

        $this->commandTester->execute(['--include-processed' => true, '--retry-errors' => true]);

        // With --retry-errors, findAllWithErrors() is used instead of
        // findAllNonCompressed(), so only the single previously-failed
        // processed file (uid 2) is picked up. No API key is configured, so
        // it still fails to compress.
        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Processed files: 0/1 compressed, 0 skipped, 1 errors', $this->commandTester->getDisplay());
    }

    #[Test]
    public function executeWithDryRunWritesNothingAndListsCandidateFiles(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile($storageUid, 'photo.jpg', 'not-a-real-jpeg-but-nonempty-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', 'image/jpeg');

        $this->commandTester->execute(['limit' => 10, '--dry-run' => true]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Dry run: nothing was written.', $display);
        self::assertStringContainsString('image/jpeg', $display);
        self::assertStringContainsString('1 files', $display);

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_error')
            ->from('sys_file')
            ->where('uid = '.$fileUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(0, (int) $row['compressed']);
        self::assertSame('', (string) $row['compress_error']);
    }

    #[Test]
    public function executeWithStorageOptionLimitsToThatStorage(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $otherStorageUid = $this->createLocalTestStorage('other');
        $this->writeRealFile($storageUid, 'photo.jpg', 'not-a-real-jpeg-but-nonempty-bytes');
        $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', 'image/jpeg');
        $this->writeRealFile($otherStorageUid, 'other.jpg', 'not-a-real-jpeg-but-nonempty-bytes', 'other');
        $this->importSysFileRow($otherStorageUid, '/other.jpg', 'other.jpg', 'image/jpeg');

        $this->commandTester->execute(['limit' => 10, '--storage' => (string) $otherStorageUid, '--dry-run' => true]);

        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('1 files', $display);
    }

    private function createLocalTestStorage(string $suffix = ''): int
    {
        $path = 'fileadmin/test'.('' !== $suffix ? '_'.$suffix : '').'/';
        GeneralUtility::mkdir_deep(Environment::getPublicPath().'/'.$path);

        return $this->get(StorageRepository::class)->createLocalStorage(
            'Test storage'.('' !== $suffix ? ' '.$suffix : ''),
            $path,
            'relative',
        );
    }

    private function writeRealFile(int $storageUid, string $fileName, string $contents, string $suffix = ''): void
    {
        $path = 'fileadmin/test'.('' !== $suffix ? '_'.$suffix : '').'/';
        GeneralUtility::writeFile(Environment::getPublicPath().'/'.$path.$fileName, $contents);
    }

    private function importSysFileRow(int $storageUid, string $identifier, string $name, string $mimeType): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file');
        $connection->insert('sys_file', [
            'pid' => 0,
            'storage' => $storageUid,
            'identifier' => $identifier,
            'identifier_hash' => sha1($identifier),
            'folder_hash' => sha1(dirname($identifier)),
            'name' => $name,
            'mime_type' => $mimeType,
            'missing' => 0,
            'compressed' => 0,
        ]);

        return (int) $connection->lastInsertId('sys_file');
    }
}
