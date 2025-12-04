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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorFactory, CompressorInterface, LocalBasicCompressor, LocalToolsCompressor, TinifyCompressor};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * CompressorFactoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressorFactory::class)]
final class CompressorFactoryTest extends TestCase
{
    private CompressorFactory $subject;
    private ContainerInterface&MockObject $containerMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $this->subject = new CompressorFactory(
            $this->containerMock,
            $this->extensionConfigurationMock,
        );
    }

    #[Test]
    public function createReturnsTinifyCompressorByDefault(): void
    {
        $tinifyCompressorMock = $this->createMock(TinifyCompressor::class);

        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getProvider')
            ->willReturn('tinify');

        $this->containerMock
            ->expects(self::once())
            ->method('get')
            ->with(TinifyCompressor::class)
            ->willReturn($tinifyCompressorMock);

        $result = $this->subject->create();

        self::assertInstanceOf(CompressorInterface::class, $result);
        self::assertSame($tinifyCompressorMock, $result);
    }

    #[Test]
    public function createReturnsLocalToolsCompressorWhenConfigured(): void
    {
        $localToolsCompressorMock = $this->createMock(LocalToolsCompressor::class);

        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getProvider')
            ->willReturn('local-tools');

        $this->containerMock
            ->expects(self::once())
            ->method('get')
            ->with(LocalToolsCompressor::class)
            ->willReturn($localToolsCompressorMock);

        $result = $this->subject->create();

        self::assertInstanceOf(CompressorInterface::class, $result);
        self::assertSame($localToolsCompressorMock, $result);
    }

    #[Test]
    public function createReturnsLocalBasicCompressorWhenConfigured(): void
    {
        $localBasicCompressorMock = $this->createMock(LocalBasicCompressor::class);

        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getProvider')
            ->willReturn('local-basic');

        $this->containerMock
            ->expects(self::once())
            ->method('get')
            ->with(LocalBasicCompressor::class)
            ->willReturn($localBasicCompressorMock);

        $result = $this->subject->create();

        self::assertInstanceOf(CompressorInterface::class, $result);
        self::assertSame($localBasicCompressorMock, $result);
    }

    #[Test]
    public function createReturnsTinifyCompressorForUnknownProvider(): void
    {
        $tinifyCompressorMock = $this->createMock(TinifyCompressor::class);

        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getProvider')
            ->willReturn('unknown-provider');

        $this->containerMock
            ->expects(self::once())
            ->method('get')
            ->with(TinifyCompressor::class)
            ->willReturn($tinifyCompressorMock);

        $result = $this->subject->create();

        self::assertSame($tinifyCompressorMock, $result);
    }
}
