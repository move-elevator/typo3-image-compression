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
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceInterface;
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
        $event = new ProcessFileListActionsEvent($resourceMock, []);

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
