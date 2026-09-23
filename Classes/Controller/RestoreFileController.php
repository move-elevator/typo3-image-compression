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
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
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
final readonly class RestoreFileController
{
    /**
     * Shared with AfterFileListRendered, which generates the token this
     * action validates.
     */
    public const FORM_PROTECTION_FORM_NAME = 'tx_typo3imagecompression_restore';
    public const FORM_PROTECTION_ACTION = 'restore';

    public function __construct(
        private RestoreService $restoreService,
        private UriBuilder $uriBuilder,
        private FlashMessageService $flashMessageService,
        private ResourceFactory $resourceFactory,
        private FormProtectionFactory $formProtectionFactory,
    ) {}

    public function mainAction(ServerRequestInterface $request): RedirectResponse
    {
        $parsedBody = (array) $request->getParsedBody();
        $fileUid = (int) ($parsedBody['fileUid'] ?? 0);
        $formToken = (string) ($parsedBody['formToken'] ?? '');

        $success = $fileUid > 0
            && $this->hasValidFormToken($formToken, $fileUid)
            && $this->currentUserMayRestore($fileUid)
            && $this->restoreService->restoreByUid($fileUid);

        $this->addFlashMessage($success);

        return new RedirectResponse((string) $this->uriBuilder->buildUriFromRoute('main'));
    }

    private function hasValidFormToken(string $formToken, int $fileUid): bool
    {
        return $this->formProtectionFactory->createForType('backend')->validateToken(
            $formToken,
            self::FORM_PROTECTION_FORM_NAME,
            self::FORM_PROTECTION_ACTION,
            (string) $fileUid,
        );
    }

    /**
     * The restore button's visibility is not an authorization boundary: it
     * only reflects whether a backup exists, not whether this user may
     * modify the file. Restoring replaces the file's content, so it needs
     * the same "replace" permission FAL itself enforces for that operation.
     */
    private function currentUserMayRestore(int $fileUid): bool
    {
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return false;
        }

        return $file->checkActionPermission('replace');
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

        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue($flashMessage);
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
