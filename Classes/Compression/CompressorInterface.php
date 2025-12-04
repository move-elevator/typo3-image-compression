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

use TYPO3\CMS\Core\Resource\{File, FileInterface};

/**
 * CompressorInterface.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface CompressorInterface
{
    /**
     * Compresses a single file.
     */
    public function compress(File|FileInterface $file): void;

    /**
     * Compresses multiple processed files.
     *
     * @param array<int, array<string, mixed>> $files Array of processed file records
     */
    public function compressProcessedFiles(array $files): void;

    /**
     * Returns the unique identifier for this compression provider.
     *
     * Example: 'tinify', 'kraken', 'shortpixel', 'local'
     */
    public function getProviderIdentifier(): string;
}
