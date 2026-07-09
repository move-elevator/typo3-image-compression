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
use MoveElevator\Typo3ImageCompression\EventListener\AfterFileReplaced;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * AfterFileReplacedTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(AfterFileReplaced::class)]
final class AfterFileReplacedTest extends TestCase
{
    #[Test]
    public function invokeCompressesFileAndReturnsEvent(): void
    {
        $fileMock = $this->createMock(FileInterface::class);
        $event = new AfterFileReplacedEvent($fileMock, '/tmp/local-file.jpg');

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->expects(self::once())->method('compress')->with($fileMock);

        $subject = new AfterFileReplaced($compressorMock);
        $result = $subject($event);

        self::assertSame($event, $result);
    }
}
