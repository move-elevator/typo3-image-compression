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

namespace MoveElevator\Typo3ImageCompression\Domain\Model;

/**
 * File.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class File extends \TYPO3\CMS\Extbase\Domain\Model\File
{
    protected int $storage = 0;
    protected bool $compressed = false;
    protected bool $compressSkipped = false;
    protected string $compressError = '';
    protected string $compressInfo = '';
    protected string $backupPath = '';
    protected string $compressProvider = '';
    protected string $compressTool = '';
    protected int $compressOriginalSize = 0;
    protected int $compressSize = 0;
    protected int $compressTstamp = 0;

    public function getStorage(): int
    {
        return $this->storage;
    }

    public function setStorage(int $storage): void
    {
        $this->storage = $storage;
    }

    public function isCompressed(): bool
    {
        return $this->compressed;
    }

    public function setCompressed(bool $compressed): void
    {
        $this->compressed = $compressed;
    }

    public function isCompressSkipped(): bool
    {
        return $this->compressSkipped;
    }

    public function setCompressSkipped(bool $compressSkipped): void
    {
        $this->compressSkipped = $compressSkipped;
    }

    public function getCompressError(): string
    {
        return $this->compressError;
    }

    public function setCompressError(string $compressError): void
    {
        $this->compressError = $compressError;
    }

    public function resetCompressError(): void
    {
        $this->setCompressError('');
    }

    public function getCompressInfo(): string
    {
        return $this->compressInfo;
    }

    public function setCompressInfo(string $compressInfo): void
    {
        $this->compressInfo = $compressInfo;
    }

    public function getCompressProvider(): string
    {
        return $this->compressProvider;
    }

    public function setCompressProvider(string $compressProvider): void
    {
        $this->compressProvider = $compressProvider;
    }

    public function getCompressTool(): string
    {
        return $this->compressTool;
    }

    public function setCompressTool(string $compressTool): void
    {
        $this->compressTool = $compressTool;
    }

    public function getCompressOriginalSize(): int
    {
        return $this->compressOriginalSize;
    }

    public function setCompressOriginalSize(int $compressOriginalSize): void
    {
        $this->compressOriginalSize = $compressOriginalSize;
    }

    public function getCompressSize(): int
    {
        return $this->compressSize;
    }

    public function setCompressSize(int $compressSize): void
    {
        $this->compressSize = $compressSize;
    }

    public function getCompressTstamp(): int
    {
        return $this->compressTstamp;
    }

    public function setCompressTstamp(int $compressTstamp): void
    {
        $this->compressTstamp = $compressTstamp;
    }

    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    public function setBackupPath(string $backupPath): void
    {
        $this->backupPath = $backupPath;
    }
}
