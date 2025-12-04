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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Domain\Model;

use MoveElevator\Typo3ImageCompression\Domain\Model\File;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * FileTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(File::class)]
final class FileTest extends TestCase
{
    private File $subject;

    protected function setUp(): void
    {
        $this->subject = new File();
    }

    #[Test]
    public function getStorageReturnsInitialValueZero(): void
    {
        self::assertSame(0, $this->subject->getStorage());
    }

    #[Test]
    public function setStorageSetsStorage(): void
    {
        $this->subject->setStorage(42);
        self::assertSame(42, $this->subject->getStorage());
    }

    #[Test]
    public function isCompressedReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isCompressed());
    }

    #[Test]
    public function setCompressedSetsCompressed(): void
    {
        $this->subject->setCompressed(true);
        self::assertTrue($this->subject->isCompressed());
    }

    #[Test]
    public function getCompressErrorReturnsInitialValueEmptyString(): void
    {
        self::assertSame('', $this->subject->getCompressError());
    }

    #[Test]
    public function setCompressErrorSetsCompressError(): void
    {
        $errorMessage = 'API limit reached';
        $this->subject->setCompressError($errorMessage);
        self::assertSame($errorMessage, $this->subject->getCompressError());
    }

    #[Test]
    public function resetCompressErrorClearsCompressError(): void
    {
        $this->subject->setCompressError('Some error');
        $this->subject->resetCompressError();
        self::assertSame('', $this->subject->getCompressError());
    }
}
