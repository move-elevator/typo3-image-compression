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
 * AvailabilityAwareInterface.
 *
 * Optional: only implemented where a provider can be temporarily unusable
 * for reasons unrelated to MIME support, such as an exhausted API quota. A
 * compressor without this interface is treated by {@see CompressorChain} as
 * always available once it declares {@see CompressorInterface::supports()}.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface AvailabilityAwareInterface
{
    /**
     * Whether this provider is currently usable, e.g. an API key is
     * configured and the quota is not exhausted.
     */
    public function isAvailable(): bool;
}
