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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Resource;

use MoveElevator\Typo3ImageCompression\Resource\Typo3FileResolver;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\{File, StorageRepository};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * Typo3FileResolverTest.
 *
 * findFileByCombinedIdentifier() is exercised against a real ResourceFactory
 * here instead of a unit-level double: ResourceFactory is declared readonly
 * from TYPO3 13.4 onwards, which PHPUnit's createMock() cannot double on
 * every resolvable PHPUnit version (see the same rationale in
 * Tests/Functional/Controller/RestoreFileControllerTest.php).
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(Typo3FileResolver::class)]
final class Typo3FileResolverTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private Typo3FileResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(Typo3FileResolver::class);
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsTheFileForAFileIdentifier(): void
    {
        $storageUid = $this->createLocalTestStorage();
        GeneralUtility::writeFile(Environment::getPublicPath().'/fileadmin/test/photo.jpg', 'bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg');

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/photo.jpg');

        self::assertInstanceOf(File::class, $result);
        self::assertSame($fileUid, $result->getUid());
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsNullForAFolderIdentifier(): void
    {
        $storageUid = $this->createLocalTestStorage();

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/');

        self::assertNull($result);
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsNullForAnIdentifierThatDoesNotResolve(): void
    {
        $storageUid = $this->createLocalTestStorage();

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/nowhere.jpg');

        self::assertNull($result);
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

    private function importSysFileRow(int $storageUid, string $identifier, string $name): int
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
        ]);

        return (int) $connection->lastInsertId('sys_file');
    }
}
