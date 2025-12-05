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

namespace MoveElevator\Typo3ImageCompression\Utility;

use Exception;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function sprintf;

/**
 * CompressionResultHandler.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CompressionResultHandler
{
    /**
     * @param array{original: array{total: int, success: int, errors: int}, processed: array{total: int, success: int, errors: int}} $stats
     */
    public static function outputToConsole(OutputInterface $output, array $stats): void
    {
        $totals = self::calculateTotals($stats);

        if (0 === $totals['files']) {
            $output->writeln('<info>No files to compress.</info>');

            return;
        }

        $output->writeln('');
        $output->writeln('<info>Compression Summary</info>');
        $output->writeln('===================');

        if ($stats['original']['total'] > 0) {
            $output->writeln(sprintf(
                'Original files: %d/%d compressed, %d errors',
                $stats['original']['success'],
                $stats['original']['total'],
                $stats['original']['errors'],
            ));
        }

        if ($stats['processed']['total'] > 0) {
            $output->writeln(sprintf(
                'Processed files: %d/%d compressed, %d errors',
                $stats['processed']['success'],
                $stats['processed']['total'],
                $stats['processed']['errors'],
            ));
        }

        $output->writeln('-------------------');
        $output->writeln(sprintf(
            '<info>Total: %d/%d compressed, %d errors</info>',
            $totals['success'],
            $totals['files'],
            $totals['errors'],
        ));
    }

    /**
     * @param array{original: array{total: int, success: int, errors: int}, processed: array{total: int, success: int, errors: int}} $stats
     */
    public static function addFlashMessage(array $stats): void
    {
        if (!self::isBackendUserLoggedIn()) {
            return;
        }

        $totals = self::calculateTotals($stats);

        if (0 === $totals['files']) {
            return;
        }

        $message = self::buildFlashMessageContent($stats, $totals);
        $severity = $totals['errors'] > 0 ? ContextualFeedbackSeverity::WARNING : ContextualFeedbackSeverity::INFO;
        $title = sprintf('Image Compression: %d/%d files compressed', $totals['success'], $totals['files']);

        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            $title,
            $severity,
            true,
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService->getMessageQueueByIdentifier()->addMessage($flashMessage);
    }

    /**
     * @param array{original: array{total: int, success: int, errors: int}, processed: array{total: int, success: int, errors: int}} $stats
     *
     * @return array{files: int, success: int, errors: int}
     */
    private static function calculateTotals(array $stats): array
    {
        return [
            'files' => $stats['original']['total'] + $stats['processed']['total'],
            'success' => $stats['original']['success'] + $stats['processed']['success'],
            'errors' => $stats['original']['errors'] + $stats['processed']['errors'],
        ];
    }

    /**
     * @param array{original: array{total: int, success: int, errors: int}, processed: array{total: int, success: int, errors: int}} $stats
     * @param array{files: int, success: int, errors: int}                                                                           $totals
     */
    private static function buildFlashMessageContent(array $stats, array $totals): string
    {
        $messageParts = [];

        if ($stats['original']['total'] > 0) {
            $messageParts[] = sprintf(
                'Original: %d/%d',
                $stats['original']['success'],
                $stats['original']['total'],
            );
        }

        if ($stats['processed']['total'] > 0) {
            $messageParts[] = sprintf(
                'Processed: %d/%d',
                $stats['processed']['success'],
                $stats['processed']['total'],
            );
        }

        $message = implode(' | ', $messageParts);

        if ($totals['errors'] > 0) {
            $message .= sprintf(' | Errors: %d', $totals['errors']);
        }

        return $message;
    }

    private static function isBackendUserLoggedIn(): bool
    {
        $context = GeneralUtility::makeInstance(Context::class);

        try {
            return (bool) $context->getPropertyFromAspect('backend.user', 'isLoggedIn');
        } catch (Exception) {
            return false;
        }
    }
}
