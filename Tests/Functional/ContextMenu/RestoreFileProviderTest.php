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
use MoveElevator\Typo3ImageCompression\Tests\Functional\Support\FileFixtureTrait;
use PHPUnit\Framework\Attributes\{CoversClass, RunClassInSeparateProcess, Test};
use TYPO3\CMS\Backend\ContextMenu\ContextMenu;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ItemProvidersRegistry;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

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
    use FileFixtureTrait;

    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'typo3/cms-filelist', 'move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][ExtensionKey::EXT_KEY]['enableBackup'] = '1';
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences(null);
        // TYPO3 v14's IconFactory resolves each SVG icon's public URL via
        // DefaultSystemResourcePublisher, which requires the request's
        // "normalizedParams" attribute; a real HTTP request always has one
        // (set by the routing middleware stack), but this manually built
        // request doesn't, and the native context menu's own FileProvider
        // renders icons for every item it offers. Without this, ANY test
        // that reaches ContextMenu::getItems() fails on v14 with a TypeError
        // before it even gets to this extension's own provider.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://typo3-testing.local/typo3/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($_SERVER));
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
}
