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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\MessageHandler;

use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use MoveElevator\Typo3ImageCompression\MessageHandler\CompressImageMessageHandler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\{File, ResourceFactory, ResourceStorage};

/**
 * CompressImageMessageHandlerTest.
 *
 * The happy path (file found, storage matches) also exercises TYPO3 core's
 * FileDeletionAspect, which touches the database. That is covered by the
 * functional test instead; these unit tests cover the guard clauses that
 * return before reaching it.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressImageMessageHandler::class)]
final class CompressImageMessageHandlerTest extends TestCase
{
    #[Test]
    public function invokeSkipsCompressionWhenFileNoLongerExists(): void
    {
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);
        $resourceFactoryMock->expects(self::once())
            ->method('getFileObject')
            ->with(42)
            ->willThrowException(new FileDoesNotExistException('gone', 1317178604));

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->expects(self::never())->method('compress');

        $subject = new CompressImageMessageHandler($resourceFactoryMock, $compressorMock);
        $subject(new CompressImageMessage(42, 7));
    }

    #[Test]
    public function invokeSkipsCompressionWhenFileStorageChangedSinceDispatch(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getUid')->willReturn(9);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getStorage')->willReturn($storageMock);

        $resourceFactoryMock = $this->createMock(ResourceFactory::class);
        $resourceFactoryMock->method('getFileObject')->with(42)->willReturn($fileMock);

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->expects(self::never())->method('compress');

        $subject = new CompressImageMessageHandler($resourceFactoryMock, $compressorMock);
        $subject(new CompressImageMessage(42, 7));
    }
}
