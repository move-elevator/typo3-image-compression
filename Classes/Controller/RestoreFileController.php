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

namespace MoveElevator\Typo3ImageCompression\Controller;

use MoveElevator\Typo3ImageCompression\Backup\RestoreService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreFileController.
 *
 * Restores a file's original from its backup, triggered from the file list's
 * restore action. POST-only, mirrors EXT:filelist's own "file_download" route.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class RestoreFileController
{
    public function __construct(
        private readonly RestoreService $restoreService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function mainAction(ServerRequestInterface $request): RedirectResponse
    {
        $parsedBody = (array) $request->getParsedBody();
        $fileUid = (int) ($parsedBody['fileUid'] ?? 0);

        $success = $fileUid > 0 && $this->restoreService->restoreByUid($fileUid);

        $this->addFlashMessage($success);

        return new RedirectResponse((string) $this->uriBuilder->buildUriFromRoute('main'));
    }

    private function addFlashMessage(bool $success): void
    {
        $key = $success ? 'flashMessage.message.restoreSuccess' : 'flashMessage.message.restoreFailed';
        $message = $this->getLanguageService()->sL(
            'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:'.$key,
        );

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            '',
            $success ? ContextualFeedbackSeverity::OK : ContextualFeedbackSeverity::ERROR,
            true,
        );

        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
