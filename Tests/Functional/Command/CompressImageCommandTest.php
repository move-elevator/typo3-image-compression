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
    public function executeCompressesOriginalFilesAndRecordsTheFailureOnTheFileWithoutApiKey(): void
    {
        // No API key is configured (the extension default is empty), so the
        // real TinyPNG call fails locally with an AccountException. That
        // exception is caught *inside* TinifyCompressor itself, which never
        // rethrows: from the command's point of view no exception escaped,
        // so it counts the file as "compressed" even though the real
        // compression attempt failed. The failure is only visible on the
        // sys_file row (compress_error), which is what this test verifies.
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile($storageUid, 'photo.jpg', 'not-a-real-jpeg-but-nonempty-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', 'image/jpeg');

        $this->commandTester->execute(['limit' => 10]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Compression Summary', $display);
        self::assertStringContainsString('Original files: 1/1 compressed, 0 errors', $display);

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compress_error')
            ->from('sys_file')
            ->where('uid = '.$fileUid)
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertStringContainsString('Provide an API key', (string) $row['compress_error']);
    }

    #[Test]
    public function executeWithIncludeProcessedOptionProcessesProcessedFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/ProcessedFiles.csv');

        $this->commandTester->execute(['--include-processed' => true]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        $display = $this->commandTester->getDisplay();
        self::assertStringContainsString('Processed files: 1/1 compressed, 0 errors', $display);
        self::assertStringContainsString('Compression Summary', $display);
    }

    #[Test]
    public function executeWithRetryErrorsOptionRetriesPreviouslyFailedProcessedFiles(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/ProcessedFiles.csv');

        $this->commandTester->execute(['--include-processed' => true, '--retry-errors' => true]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        // With --retry-errors, findAllWithErrors() is used instead of
        // findAllNonCompressed(), so only the single previously-failed
        // processed file (uid 2) is picked up.
        self::assertStringContainsString('Processed files: 1/1 compressed, 0 errors', $this->commandTester->getDisplay());
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

    private function writeRealFile(int $storageUid, string $fileName, string $contents): void
    {
        GeneralUtility::writeFile(Environment::getPublicPath().'/fileadmin/test/'.$fileName, $contents);
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
