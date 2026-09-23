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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Utility;

use MoveElevator\Typo3ImageCompression\Utility\CompressionInfoFormatter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * CompressionInfoFormatterTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionInfoFormatter::class)]
final class CompressionInfoFormatterTest extends TestCase
{
    #[Test]
    public function formatWithoutToolOmitsToolSegment(): void
    {
        self::assertSame(
            'tinify: 1 KB -> 512 B (-50%) - 01.01.2025',
            CompressionInfoFormatter::format('tinify', 1024, 512, '', 1735689600),
        );
    }

    #[Test]
    public function formatWithToolIncludesToolSegment(): void
    {
        self::assertSame(
            'local-tools (jpegoptim): 1 KB -> 512 B (-50%) - 01.01.2025',
            CompressionInfoFormatter::format('local-tools', 1024, 512, 'jpegoptim', 1735689600),
        );
    }

    #[Test]
    public function formatDefaultsTimestampToNow(): void
    {
        $expected = date('d.m.Y');

        self::assertSame(
            'tinify: 1 KB -> 512 B (-50%) - '.$expected,
            CompressionInfoFormatter::format('tinify', 1024, 512),
        );
    }

    #[Test]
    public function formatReturnsZeroPercentWhenOriginalSizeIsZero(): void
    {
        self::assertSame(
            'tinify: 0 B -> 512 B (-0%) - 01.01.2025',
            CompressionInfoFormatter::format('tinify', 0, 512, '', 1735689600),
        );
    }

    #[Test]
    public function formatReturnsZeroPercentWhenNewSizeIsZero(): void
    {
        self::assertSame(
            'tinify: 1 KB -> 0 B (-0%) - 01.01.2025',
            CompressionInfoFormatter::format('tinify', 1024, 0, '', 1735689600),
        );
    }

    #[Test]
    public function formatBytesFormatsBytes(): void
    {
        self::assertSame('500 B', CompressionInfoFormatter::formatBytes(500));
    }

    #[Test]
    public function formatBytesFormatsKilobytes(): void
    {
        self::assertSame('10 KB', CompressionInfoFormatter::formatBytes(10240));
    }

    #[Test]
    public function formatBytesFormatsMegabytes(): void
    {
        self::assertSame('2.5 MB', CompressionInfoFormatter::formatBytes((int) (2.5 * 1048576)));
    }
}
