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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\MessageHandler;

use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use MoveElevator\Typo3ImageCompression\MessageHandler\CompressImageMessageHandler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * CompressImageMessageHandlerTest.
 *
 * Exercises the real Messenger wiring from Services.yaml (the
 * `messenger.message_handler` tag) end to end, including the
 * FileDeletionAspect cleanup call, which needs a real database.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressImageMessageHandler::class)]
final class CompressImageMessageHandlerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    #[Test]
    public function dispatchingCompressImageMessageRecordsTheFailureOnTheFileWithoutApiKey(): void
    {
        // Same reasoning as CompressImageCommandTest: no API key is
        // configured, so TinifyCompressor's real call fails locally with an
        // AccountException that it catches internally. From here, no
        // exception escapes: the handler runs to completion, including the
        // FileDeletionAspect cleanup call, and the failure is only visible
        // on the sys_file row.
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile($storageUid, 'photo.jpg', 'not-a-real-jpeg-but-nonempty-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', 'image/jpeg');

        $this->get(MessageBusInterface::class)->dispatch(new CompressImageMessage($fileUid, $storageUid));

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
    public function dispatchingCompressImageMessageForUnknownFileUidDoesNotThrow(): void
    {
        $this->get(MessageBusInterface::class)->dispatch(new CompressImageMessage(999999, 1));

        self::expectNotToPerformAssertions();
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
