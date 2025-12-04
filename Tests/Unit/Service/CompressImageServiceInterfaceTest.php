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

use MoveElevator\Typo3ImageCompression\Service\CompressImageServiceInterface;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * CompressImageServiceInterfaceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversNothing]
final class CompressImageServiceInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceDefinesCompressMethod(): void
    {
        $reflection = new ReflectionClass(CompressImageServiceInterface::class);
        self::assertTrue($reflection->hasMethod('compress'));

        $method = $reflection->getMethod('compress');
        self::assertCount(1, $method->getParameters());
        self::assertSame('file', $method->getParameters()[0]->getName());
    }

    #[Test]
    public function interfaceDefinesCompressProcessedFilesMethod(): void
    {
        $reflection = new ReflectionClass(CompressImageServiceInterface::class);
        self::assertTrue($reflection->hasMethod('compressProcessedFiles'));

        $method = $reflection->getMethod('compressProcessedFiles');
        self::assertCount(1, $method->getParameters());
        self::assertSame('files', $method->getParameters()[0]->getName());
    }

    #[Test]
    public function interfaceDefinesGetProviderIdentifierMethod(): void
    {
        $reflection = new ReflectionClass(CompressImageServiceInterface::class);
        self::assertTrue($reflection->hasMethod('getProviderIdentifier'));

        $method = $reflection->getMethod('getProviderIdentifier');
        self::assertCount(0, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('string', $returnType->getName());
    }
}
