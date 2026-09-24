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
 * BeforeImageCompressionEvent.
 *
 * Dispatched by every compression provider right before it attempts to
 * compress a file, after the provider has already decided the file is
 * otherwise eligible (correct MIME type, not excluded, not in debug mode).
 *
 * A listener can veto the compression entirely via skipCompression(), or
 * adjust the quality settings the provider is about to use. The tinify
 * provider has no quality knob of its own and ignores these values, but
 * still exposes the extension's currently configured quality settings for
 * listener context.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class BeforeImageCompressionEvent
{
    private bool $compressionSkipped = false;

    public function __construct(
        private readonly File $file,
        private readonly string $provider,
        private int $jpegQuality,
        private int $pngQuality,
        private int $webpQuality,
    ) {
        $this->jpegQuality = self::clampQuality($jpegQuality);
        $this->pngQuality = self::clampQuality($pngQuality);
        $this->webpQuality = self::clampQuality($webpQuality);
    }

    public function getFile(): File
    {
        return $this->file;
    }

    /**
     * The provider that is about to run, e.g. "tinify", "local-tools", "local-basic".
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Vetoes compression for this file. The provider leaves the file
     * untouched, as if it had not matched the provider's own checks.
     */
    public function skipCompression(): void
    {
        $this->compressionSkipped = true;
    }

    public function isCompressionSkipped(): bool
    {
        return $this->compressionSkipped;
    }

    public function getJpegQuality(): int
    {
        return $this->jpegQuality;
    }

    /**
     * Clamped to 1-100, the same invariant ExtensionConfiguration enforces
     * for the configured quality settings.
     */
    public function setJpegQuality(int $jpegQuality): void
    {
        $this->jpegQuality = self::clampQuality($jpegQuality);
    }

    public function getPngQuality(): int
    {
        return $this->pngQuality;
    }

    /**
     * Clamped to 1-100, the same invariant ExtensionConfiguration enforces
     * for the configured quality settings.
     */
    public function setPngQuality(int $pngQuality): void
    {
        $this->pngQuality = self::clampQuality($pngQuality);
    }

    public function getWebpQuality(): int
    {
        return $this->webpQuality;
    }

    /**
     * Clamped to 1-100, the same invariant ExtensionConfiguration enforces
     * for the configured quality settings.
     */
    public function setWebpQuality(int $webpQuality): void
    {
        $this->webpQuality = self::clampQuality($webpQuality);
    }

    private static function clampQuality(int $quality): int
    {
        return max(1, min(100, $quality));
    }
}
