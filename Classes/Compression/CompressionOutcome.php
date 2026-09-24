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

namespace MoveElevator\Typo3ImageCompression\Compression;

/**
 * CompressionOutcome.
 *
 * Reports what a single compress() call actually did, so callers can tell
 * a deliberate skip (excluded folder, unsupported MIME type, no tool
 * available) apart from a genuine failure, instead of both looking like
 * "did not throw".
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
enum CompressionOutcome
{
    case Compressed;
    case Skipped;
    case Failed;
}
