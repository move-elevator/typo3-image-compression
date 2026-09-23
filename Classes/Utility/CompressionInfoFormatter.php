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

namespace MoveElevator\Typo3ImageCompression\Utility;

use function sprintf;

/**
 * CompressionInfoFormatter.
 *
 * Builds the human-readable compression summary (e.g. "tinify: 2.1 MB ->
 * 1.3 MB (-38%) - 04.12.2025") from the structured `sys_file` columns.
 * Presentation only: the columns are the source of truth, this is derived
 * from them on demand instead of being persisted as a string.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CompressionInfoFormatter
{
    public static function format(
        string $provider,
        int $originalSize,
        int $newSize,
        string $tool = '',
        ?int $timestamp = null,
    ): string {
        $date = date('d.m.Y', $timestamp ?? time());
        $savedPercent = self::calculateSavedPercent($originalSize, $newSize);
        $originalFormatted = self::formatBytes($originalSize);
        $newFormatted = self::formatBytes($newSize);

        if ('' !== $tool) {
            return sprintf(
                '%s (%s): %s -> %s (-%d%%) - %s',
                $provider,
                $tool,
                $originalFormatted,
                $newFormatted,
                $savedPercent,
                $date,
            );
        }

        return sprintf(
            '%s: %s -> %s (-%d%%) - %s',
            $provider,
            $originalFormatted,
            $newFormatted,
            $savedPercent,
            $date,
        );
    }

    /**
     * Formats a byte count in human-readable form (e.g. "1.3 MB").
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.1f MB', $bytes / 1048576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.0f KB', $bytes / 1024);
        }

        return sprintf('%d B', $bytes);
    }

    private static function calculateSavedPercent(int $originalSize, int $newSize): int
    {
        if ($originalSize <= 0 || $newSize <= 0) {
            return 0;
        }

        return (int) (100 - (($newSize / $originalSize) * 100));
    }
}
