<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_image_compression" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 * (c) 2025 Ronny Hauptvogel <rh@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3ImageCompression\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;

#[AsEventListener(identifier: 'typo3-image-compression-after-backend-page-renderer-event')]
/**
 * AfterFileListRendered.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class AfterFileListRendered
{
    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function __invoke(ProcessFileListActionsEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@move-elevator/typo3-image-compression/ExtendedUpload.js');
        $this->pageRenderer->addCssFile('EXT:typo3_image_compression/Resources/Public/Css/ExtendedUpload.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf');
    }
}
