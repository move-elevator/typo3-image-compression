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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\ContextMenu;

use MoveElevator\Typo3ImageCompression\Configuration as ExtensionKey;
use MoveElevator\Typo3ImageCompression\ContextMenu\ItemProviders\RestoreFileProvider;
use PHPUnit\Framework\Attributes\{CoversClass, RunClassInSeparateProcess, Test};
use TYPO3\CMS\Backend\ContextMenu\ContextMenu;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ItemProvidersRegistry;
use TYPO3\CMS\Core\Core\{Environment, SystemEnvironmentBuilder};
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * RestoreFileProviderTest.
 *
 * Exercises the real, compiled DI container instead of constructing
 * RestoreFileProvider by hand: this class relies on TYPO3\CMS\Backend's own
 * registerForAutoconfiguration(ProviderInterface::class) rule to pick up the
 * 'backend.contextmenu.itemprovider' tag with no explicit entry in this
 * extension's own Services.yaml, and getPriority()'s value only matters in
 * relation to TYPO3\CMS\Filelist\ContextMenu\ItemProviders\FileProvider's
 * fixed 100 inside ContextMenu::getAvailableProviders()'s priority-keyed
 * array (see RestoreFileProvider::getPriority()). Neither is observable by
 * constructing the class directly; both need the real registry and the real
 * FileProvider running alongside it.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreFileProvider::class)]
#[RunClassInSeparateProcess]
final class RestoreFileProviderTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'typo3/cms-filelist', 'move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][ExtensionKey::EXT_KEY]['enableBackup'] = '1';
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences(null);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://typo3-testing.local/typo3/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER'], $GLOBALS['TYPO3_REQUEST'], $GLOBALS['LANG']);

        parent::tearDown();
    }

    #[Test]
    public function isRegisteredExactlyOnceAsAContextMenuItemProvider(): void
    {
        $providers = array_filter(
            $this->get(ItemProvidersRegistry::class)->getItemProviders(),
            static fn ($provider): bool => $provider instanceof RestoreFileProvider,
        );

        self::assertCount(1, $providers);
    }

    #[Test]
    public function contextMenuIncludesRestoreForAFileWithABackupTheUserMayReplace(): void
    {
        $this->setUpBackendUser($this->importBackendUser(true));
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $backupRelativePath = $storageUid.'/backup-hash.jpg';
        $this->writeBackupFile($backupRelativePath, 'original-bytes');
        $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', $backupRelativePath);

        $items = $this->get(ContextMenu::class)->getItems('sys_file', $storageUid.':/photo.jpg');

        self::assertArrayHasKey('restore', $items);
    }

    #[Test]
    public function contextMenuDoesNotIncludeRestoreForAFileWithoutABackup(): void
    {
        $this->setUpBackendUser($this->importBackendUser(true));
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', '');

        $items = $this->get(ContextMenu::class)->getItems('sys_file', $storageUid.':/photo.jpg');

        self::assertArrayNotHasKey('restore', $items);
        // FileProvider's own items must still render: this proves
        // FileProvider (priority 100) and RestoreFileProvider (priority 40)
        // are coexisting entries, not one overwriting the other in
        // ContextMenu::getAvailableProviders()'s priority-keyed array.
        self::assertArrayHasKey('rename', $items);
        self::assertArrayHasKey('delete', $items);
    }

    private function importBackendUser(bool $isAdmin): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_users');
        $connection->insert('be_users', [
            'pid' => 0,
            'username' => $isAdmin ? 'admin' : 'restricted',
            'password' => '',
            'admin' => $isAdmin ? 1 : 0,
        ]);

        return (int) $connection->lastInsertId('be_users');
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
            'compressed' => '' === $backupPath ? 0 : 1,
            'backup_path' => $backupPath,
        ]);

        return (int) $connection->lastInsertId('sys_file');
    }
}
