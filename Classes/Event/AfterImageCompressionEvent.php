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

namespace MoveElevator\Typo3ImageCompression\Event;

use TYPO3\CMS\Core\Resource\File;

/**
 * AfterImageCompressionEvent.
 *
 * Dispatched after a file has been compressed successfully. Covers custom
 * logging, external monitoring, and notifications.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class AfterImageCompressionEvent
{
    public function __construct(
        private readonly File $file,
        private readonly string $provider,
        private readonly ?string $tool,
        private readonly int $originalSize,
        private readonly int $newSize,
    ) {}

    public function getFile(): File
    {
        return $this->file;
    }

    /**
     * The compression provider identifier (e.g. "tinify", "local-tools", "local-basic").
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * The tool used for compression (e.g. "jpegoptim", "ImageMagick"), or
     * null for providers that don't distinguish between tools (e.g. tinify).
     */
    public function getTool(): ?string
    {
        return $this->tool;
    }

    public function getOriginalSize(): int
    {
        return $this->originalSize;
    }

    public function getNewSize(): int
    {
        return $this->newSize;
    }
}
