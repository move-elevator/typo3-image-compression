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

use MoveElevator\Typo3ImageCompression\EventListener\AfterFileAdded;
use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\{File, FileInterface, Folder, ResourceStorage};

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
    public function invokeDispatchesCompressImageMessageAndReturnsEvent(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getUid')->willReturn(7);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(42);
        $fileMock->method('getStorage')->willReturn($storageMock);

        $folderMock = $this->createMock(Folder::class);
        $event = new AfterFileAddedEvent($fileMock, $folderMock);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static fn (CompressImageMessage $message): bool => 42 === $message->fileUid && 7 === $message->storageUid,
            ))
            ->willReturn(new Envelope(new CompressImageMessage(42, 7)));

        $subject = new AfterFileAdded($messageBusMock);
        $result = $subject($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function invokeSkipsDispatchForNonFileInstances(): void
    {
        $fileMock = $this->createMock(FileInterface::class);
        $folderMock = $this->createMock(Folder::class);
        $event = new AfterFileAddedEvent($fileMock, $folderMock);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(self::never())->method('dispatch');

        $subject = new AfterFileAdded($messageBusMock);
        $result = $subject($event);

        self::assertSame($event, $result);
    }
}
