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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\ContextMenu\ItemProviders;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\ContextMenu\ItemProviders\RestoreFileProvider;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use MoveElevator\Typo3ImageCompression\Resource\FileResolver;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\FormProtection\{BackendFormProtection, FormProtectionFactory};
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\File;

/**
 * RestoreFileProviderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreFileProvider::class)]
final class RestoreFileProviderTest extends TestCase
{
    private FileResolver&MockObject $fileResolverMock;
    private FileRepository&MockObject $fileRepositoryMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private UriBuilder&MockObject $uriBuilderMock;
    private FormProtectionFactory&MockObject $formProtectionFactoryMock;
    private RestoreFileProvider $subject;
    private mixed $originalLang = null;

    protected function setUp(): void
    {
        $this->fileResolverMock = $this->createMock(FileResolver::class);
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->extensionConfigurationMock->method('isBackupEnabled')->willReturn(true);
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        $this->uriBuilderMock->method('buildUriFromRoute')->with('tx_typo3imagecompression_restore')->willReturn(new Uri('/typo3-image-compression/restore'));
        $this->formProtectionFactoryMock = $this->createMock(FormProtectionFactory::class);
        $formProtectionMock = $this->createMock(BackendFormProtection::class);
        $formProtectionMock->method('generateToken')->willReturn('the-token');
        $this->formProtectionFactoryMock->method('createForType')->with('backend')->willReturn($formProtectionMock);

        $this->originalLang = $GLOBALS['LANG'] ?? null;
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('Restore original file');
        $GLOBALS['LANG'] = $languageServiceMock;

        $this->subject = new RestoreFileProvider(
            $this->fileResolverMock,
            $this->fileRepositoryMock,
            $this->extensionConfigurationMock,
            $this->uriBuilderMock,
            $this->formProtectionFactoryMock,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['LANG'] = $this->originalLang;
    }

    #[Test]
    public function canHandleReturnsTrueOnlyForSysFileTable(): void
    {
        $this->subject->setContext('sys_file', '1:/user_upload/theme.jpg');
        self::assertTrue($this->subject->canHandle());

        $this->subject->setContext('pages', '5');
        self::assertFalse($this->subject->canHandle());
    }

    #[Test]
    public function getPriorityIsBelowCoreFileProvidersPriority(): void
    {
        // TYPO3\CMS\Filelist\ContextMenu\ItemProviders\FileProvider (the core
        // provider that already handles table 'sys_file') runs at priority
        // 100 and ContextMenu::getAvailableProviders() keys its provider
        // list by priority value. An equal priority here would silently
        // overwrite (not merge with) that entry in the keyed array,
        // wiping out the entire native file context menu.
        self::assertLessThan(100, $this->subject->getPriority());
    }

    #[Test]
    public function addItemsLeavesItemsUnchangedWhenBackupsAreDisabled(): void
    {
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->extensionConfigurationMock->method('isBackupEnabled')->willReturn(false);
        $this->subject = new RestoreFileProvider(
            $this->fileResolverMock,
            $this->fileRepositoryMock,
            $this->extensionConfigurationMock,
            $this->uriBuilderMock,
            $this->formProtectionFactoryMock,
        );
        $this->fileResolverMock->expects(self::never())->method('findFileByCombinedIdentifier');

        $this->subject->setContext('sys_file', '1:/user_upload/theme.jpg');

        self::assertSame(['existing' => []], $this->subject->addItems(['existing' => []]));
    }

    #[Test]
    public function addItemsLeavesItemsUnchangedWhenIdentifierDoesNotResolveToAFile(): void
    {
        $this->fileResolverMock->method('findFileByCombinedIdentifier')->with('1:/user_upload/')->willReturn(null);

        $this->subject->setContext('sys_file', '1:/user_upload/');

        self::assertSame([], $this->subject->addItems([]));
    }

    #[Test]
    public function addItemsLeavesItemsUnchangedWhenFileHasNoBackup(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $this->fileResolverMock->method('findFileByCombinedIdentifier')->willReturn($fileMock);
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(5)->willReturn(null);

        $this->subject->setContext('sys_file', '1:/user_upload/theme.jpg');

        self::assertSame([], $this->subject->addItems([]));
    }

    #[Test]
    public function addItemsLeavesItemsUnchangedWhenCurrentUserMayNotReplaceTheFile(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $fileMock->method('checkActionPermission')->with('replace')->willReturn(false);
        $this->fileResolverMock->method('findFileByCombinedIdentifier')->willReturn($fileMock);
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(5)->willReturn('1/hash.jpg');

        $this->subject->setContext('sys_file', '1:/user_upload/theme.jpg');

        self::assertSame([], $this->subject->addItems([]));
    }

    #[Test]
    public function addItemsAppendsARestoreItemWhenTheFileHasABackupAndMayBeReplaced(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $fileMock->method('checkActionPermission')->with('replace')->willReturn(true);
        $this->fileResolverMock->method('findFileByCombinedIdentifier')->with('1:/user_upload/theme.jpg')->willReturn($fileMock);
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(5)->willReturn('1/hash.jpg');

        $this->subject->setContext('sys_file', '1:/user_upload/theme.jpg');

        $items = $this->subject->addItems(['replaceFile' => ['type' => 'item']]);

        self::assertSame(['type' => 'item'], $items['replaceFile']);
        self::assertArrayHasKey('restore', $items);
        self::assertSame('item', $items['restore']['type']);
        self::assertSame('Restore original file', $items['restore']['label']);
        self::assertSame('restoreFile', $items['restore']['callbackAction']);
        self::assertSame(
            '@move-elevator/typo3-image-compression/RestoreFileContextMenuAction',
            $items['restore']['additionalAttributes']['data-callback-module'],
        );
        self::assertSame('/typo3-image-compression/restore', $items['restore']['additionalAttributes']['data-restorefileaction-url']);
        self::assertSame('5', $items['restore']['additionalAttributes']['data-restorefileaction-uid']);
        self::assertSame('the-token', $items['restore']['additionalAttributes']['data-restorefileaction-token']);
    }
}
