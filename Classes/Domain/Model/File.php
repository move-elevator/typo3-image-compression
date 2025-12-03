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
    protected string $compressError = '';

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
}
