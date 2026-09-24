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

use MoveElevator\Typo3ImageCompression\Compression\{AvailabilityAwareInterface, CompressorChain, CompressorInterface};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\File;

/**
 * CompressorChainTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressorChain::class)]
final class CompressorChainTest extends TestCase
{
    #[Test]
    public function supportsIsTrueWhenAnyMemberSupportsTheMimeType(): void
    {
        $first = $this->createMock(CompressorInterface::class);
        $first->method('supports')->with('image/gif')->willReturn(false);

        $second = $this->createMock(CompressorInterface::class);
        $second->method('supports')->with('image/gif')->willReturn(true);

        $subject = new CompressorChain([$first, $second]);

        self::assertTrue($subject->supports('image/gif'));
    }

    #[Test]
    public function supportsIsFalseWhenNoMemberSupportsTheMimeType(): void
    {
        $first = $this->createMock(CompressorInterface::class);
        $first->method('supports')->willReturn(false);

        $subject = new CompressorChain([$first]);

        self::assertFalse($subject->supports('image/svg+xml'));
    }

    #[Test]
    public function compressDelegatesToTheFirstSupportingCompressor(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getMimeType')->willReturn('image/jpeg');

        $unsupporting = $this->createMock(CompressorInterface::class);
        $unsupporting->method('supports')->with('image/jpeg')->willReturn(false);
        $unsupporting->expects(self::never())->method('compress');

        $supporting = $this->createMock(CompressorInterface::class);
        $supporting->method('supports')->with('image/jpeg')->willReturn(true);
        $supporting->expects(self::once())->method('compress')->with($fileMock);

        $subject = new CompressorChain([$unsupporting, $supporting]);
        $subject->compress($fileMock);
    }

    #[Test]
    public function compressSkipsASupportingButUnavailableCompressorInFavorOfTheNext(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getMimeType')->willReturn('image/jpeg');

        /** @var AvailabilityAwareInterface&CompressorInterface&MockObject $exhausted */
        $exhausted = $this->createMockForIntersectionOfInterfaces([CompressorInterface::class, AvailabilityAwareInterface::class]);
        $exhausted->method('supports')->willReturn(true);
        $exhausted->method('isAvailable')->willReturn(false);
        $exhausted->expects(self::never())->method('compress');

        $fallback = $this->createMock(CompressorInterface::class);
        $fallback->method('supports')->willReturn(true);
        $fallback->expects(self::once())->method('compress')->with($fileMock);

        $subject = new CompressorChain([$exhausted, $fallback]);
        $subject->compress($fileMock);
    }

    #[Test]
    public function compressStillDelegatesToTheLastSupportingCompressorWhenAllAreUnavailable(): void
    {
        // Rather than doing nothing: with no other provider configured, the
        // sole (unavailable) provider should still run, so its normal
        // failure path records the reason on the file, instead of the file
        // silently staying uncompressed with no error at all.
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getMimeType')->willReturn('image/jpeg');

        /** @var AvailabilityAwareInterface&CompressorInterface&MockObject $exhausted */
        $exhausted = $this->createMockForIntersectionOfInterfaces([CompressorInterface::class, AvailabilityAwareInterface::class]);
        $exhausted->method('supports')->willReturn(true);
        $exhausted->method('isAvailable')->willReturn(false);
        $exhausted->expects(self::once())->method('compress')->with($fileMock);

        $subject = new CompressorChain([$exhausted]);
        $subject->compress($fileMock);
    }

    #[Test]
    public function compressDoesNothingWhenNoCompressorSupportsTheMimeType(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getMimeType')->willReturn('image/svg+xml');

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->method('supports')->willReturn(false);
        $compressorMock->expects(self::never())->method('compress');

        $subject = new CompressorChain([$compressorMock]);
        $subject->compress($fileMock);
    }

    #[Test]
    public function compressLowercasesTheMimeTypeBeforeMatching(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getMimeType')->willReturn('IMAGE/JPEG');

        $compressorMock = $this->createMock(CompressorInterface::class);
        $compressorMock->method('supports')->with('image/jpeg')->willReturn(true);
        $compressorMock->expects(self::once())->method('compress')->with($fileMock);

        $subject = new CompressorChain([$compressorMock]);
        $subject->compress($fileMock);
    }

    #[Test]
    public function compressProcessedFilesDelegatesToTheFirstConfiguredCompressor(): void
    {
        $files = [['uid' => 42]];

        $primary = $this->createMock(CompressorInterface::class);
        $primary->expects(self::once())->method('compressProcessedFiles')->with($files);

        $secondary = $this->createMock(CompressorInterface::class);
        $secondary->expects(self::never())->method('compressProcessedFiles');

        $subject = new CompressorChain([$primary, $secondary]);
        $subject->compressProcessedFiles($files);
    }

    #[Test]
    public function getProviderIdentifierReturnsChain(): void
    {
        $compressorMock = $this->createMock(CompressorInterface::class);
        $subject = new CompressorChain([$compressorMock]);

        self::assertSame('chain', $subject->getProviderIdentifier());
    }
}
