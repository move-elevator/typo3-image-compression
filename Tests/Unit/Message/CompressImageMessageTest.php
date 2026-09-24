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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Message;

use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * CompressImageMessageTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressImageMessage::class)]
final class CompressImageMessageTest extends TestCase
{
    #[Test]
    public function constructorExposesFileAndStorageUid(): void
    {
        $message = new CompressImageMessage(42, 7);

        self::assertSame(42, $message->fileUid);
        self::assertSame(7, $message->storageUid);
    }
}
