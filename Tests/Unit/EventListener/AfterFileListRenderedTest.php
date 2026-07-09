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

use MoveElevator\Typo3ImageCompression\EventListener\AfterFileListRendered;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentGroup;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceInterface;
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
    protected function setUp(): void
    {
        // PageRenderer's class-property defaults reference the global LF
        // constant, which is normally defined during TYPO3's bootstrap. This
        // pure unit test only boots vendor/autoload.php, so it must be defined
        // manually before PageRenderer can be mocked/loaded.
        if (!defined('LF')) {
            define('LF', chr(10));
        }
    }

    #[Test]
    public function invokeRegistersJavaScriptCssAndLanguageLabels(): void
    {
        $resourceMock = $this->createMock(ResourceInterface::class);

        // ProcessFileListActionsEvent's constructor was reshaped in TYPO3 v14
        // (icons/actions are now grouped via ComponentGroup, and a PSR-7
        // request was added) instead of the plain (resource, actionItems)
        // shape used in v12/v13.
        $majorVersion = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
        if ($majorVersion >= 14) {
            $primaryGroup = new ComponentGroup('primary'); // @phpstan-ignore class.notFound (TYPO3 v14-only class, not part of the pinned v12 API this is analysed against)
            $secondaryGroup = new ComponentGroup('secondary'); // @phpstan-ignore class.notFound (TYPO3 v14-only class, not part of the pinned v12 API this is analysed against)
            $event = new ProcessFileListActionsEvent($primaryGroup, $secondaryGroup, $resourceMock, $this->createMock(RequestInterface::class)); // @phpstan-ignore argument.type, argument.type, arguments.count (TYPO3 v14-only constructor shape, not part of the pinned v12 API this is analysed against)
        } else {
            $event = new ProcessFileListActionsEvent($resourceMock, []);
        }

        $pageRendererMock = $this->createMock(PageRenderer::class);
        $pageRendererMock
            ->expects(self::once())
            ->method('loadJavaScriptModule')
            ->with('@move-elevator/typo3-image-compression/ExtendedUpload.js');
        $pageRendererMock
            ->expects(self::once())
            ->method('addCssFile')
            ->with('EXT:typo3_image_compression/Resources/Public/Css/ExtendedUpload.css');
        $pageRendererMock
            ->expects(self::once())
            ->method('addInlineLanguageLabelFile')
            ->with('EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf');

        $subject = new AfterFileListRendered($pageRendererMock);
        $subject($event);
    }
}
