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

namespace MoveElevator\Typo3ImageCompression\Compression\Exception;

use RuntimeException;

/**
 * CompressionAbortedException.
 *
 * Signals that a compression provider hit an unrecoverable, run-wide problem
 * (e.g. an invalid TinyPNG API key or an exhausted quota) rather than a
 * per-file failure. The caller must stop processing further files instead
 * of recording an error for the current file and moving on to the next one.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class CompressionAbortedException extends RuntimeException {}
