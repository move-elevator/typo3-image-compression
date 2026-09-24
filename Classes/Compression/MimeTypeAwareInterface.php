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
 * MimeTypeAwareInterface.
 *
 * Optional interface for providers whose effectively supported MIME types
 * differ from the configured `mimeTypes` extension setting, e.g. because a
 * type is only supported once an external tool is detected on the system.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface MimeTypeAwareInterface
{
    /**
     * Returns the MIME types this provider actually compresses right now,
     * i.e. the configured `mimeTypes` setting plus any zero-configuration
     * additions.
     *
     * @return string[]
     */
    public function getSupportedMimeTypes(): array;
}
