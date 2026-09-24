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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\EventListener;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use MoveElevator\Typo3ImageCompression\EventListener\AfterFileListRendered;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ComponentGroup;
use TYPO3\CMS\Core\FormProtection\{BackendFormProtection, FormProtectionFactory};
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\{File, ResourceInterface};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;

use function chr;
use function define;
use function defined;

/**
 * AfterFileListRenderedTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AfterFileListRendered::class)]
final class AfterFileListRenderedTest extends TestCase
{
    private FileRepository&MockObject $fileRepositoryMock;
    private UriBuilder&MockObject $uriBuilderMock;
    private IconFactory $iconFactoryMock;
    private PageRenderer&MockObject $pageRendererMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private FormProtectionFactory&MockObject $formProtectionFactoryMock;
    private AfterFileListRendered $subject;
    private mixed $originalLang = null;

    protected function setUp(): void
    {
        // PageRenderer's class-property defaults reference the global LF
        // constant, which is normally defined during TYPO3's bootstrap. This
        // pure unit test only boots vendor/autoload.php, so it must be defined
        // manually before PageRenderer can be mocked/loaded.
        if (!defined('LF')) {
            define('LF', chr(10));
        }

        // IconFactory's own constructor (called by IconFactoryTestDoubleFactory)
        // reads these on TYPO3 v12.4, normally populated during TYPO3's bootstrap.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['IconFactory']['recordStatusMapping'] ??= [];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['IconFactory']['overlayPriorities'] ??= [];

        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        // See IconFactoryTestDoubleFactory for why this isn't a plain PHPUnit mock.
        $this->iconFactoryMock = IconFactoryTestDoubleFactory::create('actions-delete-restore');
        $this->pageRendererMock = $this->createMock(PageRenderer::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->extensionConfigurationMock->method('isBackupEnabled')->willReturn(true);
        $this->formProtectionFactoryMock = $this->createMock(FormProtectionFactory::class);
        $formProtectionMock = $this->createMock(BackendFormProtection::class);
        $formProtectionMock->method('generateToken')->willReturn('the-token');
        $this->formProtectionFactoryMock->method('createForType')->with('backend')->willReturn($formProtectionMock);

        $this->originalLang = $GLOBALS['LANG'] ?? null;
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('Restore original file');
        $GLOBALS['LANG'] = $languageServiceMock;

        $this->subject = new AfterFileListRendered(
            $this->pageRendererMock,
            $this->fileRepositoryMock,
            $this->uriBuilderMock,
            $this->iconFactoryMock,
            $this->extensionConfigurationMock,
            $this->formProtectionFactoryMock,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['LANG'] = $this->originalLang;
    }

    #[Test]
    public function invokeRegistersJavaScriptCssAndLanguageLabels(): void
    {
        $resourceMock = $this->createMock(ResourceInterface::class);
        $event = $this->createEvent($resourceMock);

        $this->pageRendererMock
            ->expects(self::exactly(2))
            ->method('loadJavaScriptModule')
            ->with(self::logicalOr(
                '@move-elevator/typo3-image-compression/ExtendedUpload.js',
                '@move-elevator/typo3-image-compression/RestoreFileAction.js',
            ));
        $this->pageRendererMock
            ->expects(self::once())
            ->method('addCssFile')
            ->with('EXT:typo3_image_compression/Resources/Public/Css/ExtendedUpload.css');
        $this->pageRendererMock
            ->expects(self::once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf');

        ($this->subject)($event);
    }

    #[Test]
    public function invokeDoesNothingForNonFileResources(): void
    {
        if ($this->isV14OrHigher()) {
            self::markTestSkipped('setActionItems()/getActionItems() are not part of the v14 event shape.');
        }

        $resourceMock = $this->createMock(ResourceInterface::class);
        $event = $this->createEvent($resourceMock);

        $this->fileRepositoryMock->expects(self::never())->method('findBackupPathByUid');

        ($this->subject)($event);

        self::assertSame([], $event->getActionItems());
    }

    #[Test]
    public function invokeDoesNotAddRestoreActionWhenFileHasNoBackup(): void
    {
        if ($this->isV14OrHigher()) {
            self::markTestSkipped('setActionItems()/getActionItems() are not part of the v14 event shape.');
        }

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(5)->willReturn(null);

        $event = $this->createEvent($fileMock);

        ($this->subject)($event);

        self::assertSame([], $event->getActionItems());
    }

    #[Test]
    public function invokeAddsRestoreActionWhenFileHasABackup(): void
    {
        if ($this->isV14OrHigher()) {
            self::markTestSkipped('setActionItems()/getActionItems() are not part of the v14 event shape.');
        }

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $this->fileRepositoryMock->method('findBackupPathByUid')->with(5)->willReturn('1/hash.jpg');
        $this->uriBuilderMock->method('buildUriFromRoute')->with('tx_typo3imagecompression_restore')->willReturn(new Uri('/typo3-image-compression/restore'));

        $event = $this->createEvent($fileMock);

        ($this->subject)($event);

        self::assertArrayHasKey('restore', $event->getActionItems());
        // The token itself is covered by RestoreButtonTest; asserting on its
        // rendered HTML here would additionally require a fully working
        // Icon (IconFactoryTestDoubleFactory's is deliberately minimal).
    }

    #[Test]
    public function invokeDoesNotQueryOrAddARestoreActionWhenBackupsAreDisabled(): void
    {
        if ($this->isV14OrHigher()) {
            self::markTestSkipped('setActionItems()/getActionItems() are not part of the v14 event shape.');
        }

        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->extensionConfigurationMock->method('isBackupEnabled')->willReturn(false);
        $this->subject = new AfterFileListRendered(
            $this->pageRendererMock,
            $this->fileRepositoryMock,
            $this->uriBuilderMock,
            $this->iconFactoryMock,
            $this->extensionConfigurationMock,
            $this->formProtectionFactoryMock,
        );

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(5);
        $this->fileRepositoryMock->expects(self::never())->method('findBackupPathByUid');

        $event = $this->createEvent($fileMock);

        ($this->subject)($event);

        self::assertSame([], $event->getActionItems());
    }

    private function createEvent(ResourceInterface $resource): ProcessFileListActionsEvent
    {
        // ProcessFileListActionsEvent's constructor was reshaped in TYPO3 v14
        // (icons/actions are now grouped via ComponentGroup, and a PSR-7
        // request was added) instead of the plain (resource, actionItems)
        // shape used in v12/v13.
        if ($this->isV14OrHigher()) {
            $primaryGroup = new ComponentGroup('primary');
            $secondaryGroup = new ComponentGroup('secondary');

            return new ProcessFileListActionsEvent($primaryGroup, $secondaryGroup, $resource, $this->createMock(RequestInterface::class));
        }

        return new ProcessFileListActionsEvent($resource, []);
    }

    private function isV14OrHigher(): bool
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 14;
    }
}
