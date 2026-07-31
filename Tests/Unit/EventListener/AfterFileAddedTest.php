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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\EventListener;

use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use MoveElevator\Typo3ImageCompression\EventListener\AfterFileAdded;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\{FileInterface, Folder};

/**
 * AfterFileAddedTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AfterFileAdded::class)]
final class AfterFileAddedTest extends TestCase
{
    #[Test]
    public function invokeCompressesFileAndReturnsEvent(): void
    {
        $fileMock = $this->createMock(FileInterface::class);
        $folderMock = $this->createMock(Folder::class);
        $event = new AfterFileAddedEvent($fileMock, $folderMock);

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->expects(self::once())->method('compress')->with($fileMock);

        $subject = new AfterFileAdded($compressorMock);
        $result = $subject($event);

        self::assertSame($event, $result);
    }
}
