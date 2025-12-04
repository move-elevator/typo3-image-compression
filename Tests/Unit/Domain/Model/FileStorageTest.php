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

use MoveElevator\Typo3ImageCompression\Domain\Model\FileStorage;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;


/**
 * FileStorageTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */

#[CoversClass(FileStorage::class)]
final class FileStorageTest extends TestCase
{
    private FileStorage $subject;

    protected function setUp(): void
    {
        $this->subject = new FileStorage();
    }

    #[Test]
    public function getNameReturnsInitialValueEmptyString(): void
    {
        self::assertSame('', $this->subject->getName());
    }

    #[Test]
    public function setNameSetsName(): void
    {
        $this->subject->setName('fileadmin');
        self::assertSame('fileadmin', $this->subject->getName());
    }

    #[Test]
    public function getDescriptionReturnsInitialValueEmptyString(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    #[Test]
    public function setDescriptionSetsDescription(): void
    {
        $description = 'Default file storage';
        $this->subject->setDescription($description);
        self::assertSame($description, $this->subject->getDescription());
    }

    #[Test]
    public function isDefaultReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isDefault());
    }

    #[Test]
    public function setDefaultSetsDefault(): void
    {
        $this->subject->setDefault(true);
        self::assertTrue($this->subject->isDefault());
    }

    #[Test]
    public function isBrowsableReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isBrowsable());
    }

    #[Test]
    public function setBrowsableSetsBrowsable(): void
    {
        $this->subject->setBrowsable(true);
        self::assertTrue($this->subject->isBrowsable());
    }

    #[Test]
    public function isPublicReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isPublic());
    }

    #[Test]
    public function setPublicSetsPublic(): void
    {
        $this->subject->setPublic(true);
        self::assertTrue($this->subject->isPublic());
    }

    #[Test]
    public function isWritableReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isWritable());
    }

    #[Test]
    public function setWritableSetsWritable(): void
    {
        $this->subject->setWritable(true);
        self::assertTrue($this->subject->isWritable());
    }

    #[Test]
    public function isOnlineReturnsInitialValueFalse(): void
    {
        self::assertFalse($this->subject->isOnline());
    }

    #[Test]
    public function setOnlineSetsOnline(): void
    {
        $this->subject->setOnline(true);
        self::assertTrue($this->subject->isOnline());
    }
}
