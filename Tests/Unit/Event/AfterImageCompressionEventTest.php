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

use MoveElevator\Typo3ImageCompression\Event\AfterImageCompressionEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\File;

/**
 * AfterImageCompressionEventTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AfterImageCompressionEvent::class)]
final class AfterImageCompressionEventTest extends TestCase
{
    #[Test]
    public function exposesAllConstructorValues(): void
    {
        $fileMock = $this->createMock(File::class);
        $event = new AfterImageCompressionEvent($fileMock, 'local-tools', 'jpegoptim', 2048, 1024);

        self::assertSame($fileMock, $event->getFile());
        self::assertSame('local-tools', $event->getProvider());
        self::assertSame('jpegoptim', $event->getTool());
        self::assertSame(2048, $event->getOriginalSize());
        self::assertSame(1024, $event->getNewSize());
    }

    #[Test]
    public function toolIsNullableForProvidersWithoutADistinctTool(): void
    {
        $event = new AfterImageCompressionEvent($this->createMock(File::class), 'tinify', null, 2048, 1024);

        self::assertNull($event->getTool());
    }
}
