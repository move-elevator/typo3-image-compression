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

namespace MoveElevator\Typo3ImageCompression\EventListener;

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3ImageCompression\Backend\RestoreButton;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\{Icon, IconFactory};
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;

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
        private FileRepository $fileRepository,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
    ) {}

    /**
     * @throws Exception
     */
    public function __invoke(ProcessFileListActionsEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@move-elevator/typo3-image-compression/ExtendedUpload.js');
        $this->pageRenderer->addCssFile('EXT:typo3_image_compression/Resources/Public/Css/ExtendedUpload.css');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf');

        $this->addRestoreAction($event);
    }

    /**
     * @throws Exception
     */
    private function addRestoreAction(ProcessFileListActionsEvent $event): void
    {
        // TYPO3 v14 restructured this event around ComponentGroup objects
        // instead of a flat, mutable action-items array; guard against that
        // shape instead of hard-depending on the v12/v13 API this extension
        // still primarily targets. PHPStan only sees the v12 shape installed
        // here and considers the guard always true, which is exactly why it
        // has to stay a runtime check rather than a static one (see
        // Tests/CGL/phpstan-baseline.neon for the matching ignore entries).
        if (!method_exists($event, 'getActionItems') || !method_exists($event, 'setActionItems')) {
            return;
        }

        $resource = $event->getResource();

        if (!$resource instanceof File) {
            return;
        }

        $fileUid = $resource->getUid();

        if ($fileUid <= 0 || null === $this->fileRepository->findBackupPathByUid($fileUid)) {
            return;
        }

        $actionItems = $event->getActionItems();
        $actionItems['restore'] = new RestoreButton(
            (string) $this->uriBuilder->buildUriFromRoute('tx_typo3imagecompression_restore'),
            $fileUid,
            $this->getLanguageService()->sL('LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:restoreAction'),
            $this->iconFactory->getIcon('actions-delete-restore', Icon::SIZE_SMALL),
        );
        $event->setActionItems($actionItems);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
