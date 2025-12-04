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

namespace MoveElevator\Typo3ImageCompression\Compression;

use MoveElevator\Typo3ImageCompression\Configuration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * FlashMessageTrait.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
trait FlashMessageTrait
{
    /**
     * Adds a localized flash message to the TYPO3 backend message queue.
     *
     * Messages are only displayed in backend context (skipped in CLI mode).
     * The message key is used to look up translations from the extension's
     * locallang.xlf file.
     *
     * @param string                     $key            Translation key (without prefix)
     * @param array<int, string>         $replaceMarkers Values to replace in the translation
     * @param ContextualFeedbackSeverity $severity       Message severity level
     *
     * @throws Exception
     */
    protected function addFlashMessage(
        string $key,
        array $replaceMarkers = [],
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR,
    ): void {
        if (Environment::isCli()) {
            return;
        }

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            LocalizationUtility::translate(
                'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:flashMessage.message.'.$key,
                null,
                $replaceMarkers,
            ),
            LocalizationUtility::translate(
                'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:flashMessage.title',
            ),
            $severity,
            true,
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }
}
