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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression;

use MoveElevator\Typo3ImageCompression\Compression\AvailabilityAwareInterface;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * AvailabilityAwareInterfaceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversNothing]
final class AvailabilityAwareInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDefinesIsAvailableMethod(): void
    {
        $reflection = new ReflectionClass(AvailabilityAwareInterface::class);
        self::assertTrue($reflection->hasMethod('isAvailable'));

        $method = $reflection->getMethod('isAvailable');
        self::assertCount(0, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }
}
