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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit;

use MoveElevator\Typo3ImageCompression\Configuration;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    #[Test]
    public function extKeyConstantHasExpectedValue(): void
    {
        self::assertSame('typo3_image_compression', Configuration::EXT_KEY);
    }

    #[Test]
    public function extNameConstantHasExpectedValue(): void
    {
        self::assertSame('Typo3ImageCompression', Configuration::EXT_NAME);
    }
}
