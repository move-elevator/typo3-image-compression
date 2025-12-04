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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Service;

use MoveElevator\Typo3ImageCompression\Service\CompressionQuotaAwareInterface;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * CompressionQuotaAwareInterfaceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversNothing]
final class CompressionQuotaAwareInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDefinesGetCompressionCountMethod(): void
    {
        $reflection = new ReflectionClass(CompressionQuotaAwareInterface::class);
        self::assertTrue($reflection->hasMethod('getCompressionCount'));

        $method = $reflection->getMethod('getCompressionCount');
        self::assertCount(0, $method->getParameters());
        self::assertTrue($method->getReturnType()?->allowsNull());
    }

    #[Test]
    public function interfaceDefinesGetQuotaLimitMethod(): void
    {
        $reflection = new ReflectionClass(CompressionQuotaAwareInterface::class);
        self::assertTrue($reflection->hasMethod('getQuotaLimit'));

        $method = $reflection->getMethod('getQuotaLimit');
        self::assertCount(0, $method->getParameters());
        self::assertTrue($method->getReturnType()?->allowsNull());
    }
}
