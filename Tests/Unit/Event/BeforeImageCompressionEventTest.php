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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Event;

use MoveElevator\Typo3ImageCompression\Event\BeforeImageCompressionEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\File;

/**
 * BeforeImageCompressionEventTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(BeforeImageCompressionEvent::class)]
final class BeforeImageCompressionEventTest extends TestCase
{
    #[Test]
    public function exposesFileAndProvider(): void
    {
        $fileMock = $this->createMock(File::class);
        $event = new BeforeImageCompressionEvent($fileMock, 'tinify', 80, 85, 75);

        self::assertSame($fileMock, $event->getFile());
        self::assertSame('tinify', $event->getProvider());
    }

    #[Test]
    public function isCompressionSkippedDefaultsToFalse(): void
    {
        $event = new BeforeImageCompressionEvent($this->createMock(File::class), 'local-tools', 80, 85, 75);

        self::assertFalse($event->isCompressionSkipped());
    }

    #[Test]
    public function skipCompressionMarksCompressionAsSkipped(): void
    {
        $event = new BeforeImageCompressionEvent($this->createMock(File::class), 'local-tools', 80, 85, 75);

        $event->skipCompression();

        self::assertTrue($event->isCompressionSkipped());
    }

    #[Test]
    public function qualitySettersOverrideConstructorValues(): void
    {
        $event = new BeforeImageCompressionEvent($this->createMock(File::class), 'local-basic', 80, 85, 75);

        $event->setJpegQuality(60);
        $event->setPngQuality(70);
        $event->setWebpQuality(50);

        self::assertSame(60, $event->getJpegQuality());
        self::assertSame(70, $event->getPngQuality());
        self::assertSame(50, $event->getWebpQuality());
    }

    #[Test]
    public function qualityGettersReturnConstructorValuesWhenNotOverridden(): void
    {
        $event = new BeforeImageCompressionEvent($this->createMock(File::class), 'local-basic', 80, 85, 75);

        self::assertSame(80, $event->getJpegQuality());
        self::assertSame(85, $event->getPngQuality());
        self::assertSame(75, $event->getWebpQuality());
    }
}
