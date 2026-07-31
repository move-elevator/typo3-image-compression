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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Configuration;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ExtensionConfigurationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ExtensionConfiguration::class)]
final class ExtensionConfigurationTest extends TestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function getProviderReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['provider' => 'local-tools']);

        self::assertSame('local-tools', $subject->getProvider());
    }

    #[Test]
    public function getProviderDefaultsToTinify(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame(ExtensionConfiguration::PROVIDER_TINIFY, $subject->getProvider());
    }

    #[Test]
    public function getApiKeyReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['apiKey' => 'secret-key']);

        self::assertSame('secret-key', $subject->getApiKey());
    }

    #[Test]
    public function getApiKeyDefaultsToEmptyString(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame('', $subject->getApiKey());
    }

    #[Test]
    public function isDebugReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['debug' => true]);

        self::assertTrue($subject->isDebug());
    }

    #[Test]
    public function isDebugDefaultsToFalse(): void
    {
        $subject = $this->createSubject([]);

        self::assertFalse($subject->isDebug());
    }

    #[Test]
    public function getExcludeFoldersReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['excludeFolders' => 'folder1, folder2']);

        self::assertSame(['folder1', 'folder2'], $subject->getExcludeFolders());
    }

    #[Test]
    public function getExcludeFoldersDefaultsToEmptyArray(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame([], $subject->getExcludeFolders());
    }

    #[Test]
    public function getMimeTypesReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['mimeTypes' => 'image/jpeg, image/png']);

        self::assertSame(['image/jpeg', 'image/png'], $subject->getMimeTypes());
    }

    #[Test]
    public function getMimeTypesDefaultsToEmptyArray(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame([], $subject->getMimeTypes());
    }

    #[Test]
    public function isSystemInformationToolbarReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['systemInformationToolbar' => true]);

        self::assertTrue($subject->isSystemInformationToolbar());
    }

    #[Test]
    public function isSystemInformationToolbarDefaultsToFalse(): void
    {
        $subject = $this->createSubject([]);

        self::assertFalse($subject->isSystemInformationToolbar());
    }

    #[Test]
    public function isShowCompressionStatusReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['showCompressionStatus' => false]);

        self::assertFalse($subject->isShowCompressionStatus());
    }

    #[Test]
    public function isShowCompressionStatusDefaultsToTrue(): void
    {
        $subject = $this->createSubject([]);

        self::assertTrue($subject->isShowCompressionStatus());
    }

    #[Test]
    public function isShowStatusReportReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['showStatusReport' => false]);

        self::assertFalse($subject->isShowStatusReport());
    }

    #[Test]
    public function isShowStatusReportDefaultsToTrue(): void
    {
        $subject = $this->createSubject([]);

        self::assertTrue($subject->isShowStatusReport());
    }

    #[Test]
    public function getJpegQualityReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['jpegQuality' => 42]);

        self::assertSame(42, $subject->getJpegQuality());
    }

    #[Test]
    public function getJpegQualityDefaultsTo85(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame(85, $subject->getJpegQuality());
    }

    #[Test]
    public function getJpegQualityClampsBelowRangeToOne(): void
    {
        $subject = $this->createSubject(['jpegQuality' => -5]);

        self::assertSame(1, $subject->getJpegQuality());
    }

    #[Test]
    public function getJpegQualityClampsAboveRangeToHundred(): void
    {
        $subject = $this->createSubject(['jpegQuality' => 500]);

        self::assertSame(100, $subject->getJpegQuality());
    }

    #[Test]
    public function getPngQualityReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['pngQuality' => 42]);

        self::assertSame(42, $subject->getPngQuality());
    }

    #[Test]
    public function getPngQualityDefaultsTo85(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame(85, $subject->getPngQuality());
    }

    #[Test]
    public function getPngQualityClampsBelowRangeToOne(): void
    {
        $subject = $this->createSubject(['pngQuality' => -5]);

        self::assertSame(1, $subject->getPngQuality());
    }

    #[Test]
    public function getPngQualityClampsAboveRangeToHundred(): void
    {
        $subject = $this->createSubject(['pngQuality' => 500]);

        self::assertSame(100, $subject->getPngQuality());
    }

    #[Test]
    public function getWebpQualityReturnsConfiguredValue(): void
    {
        $subject = $this->createSubject(['webpQuality' => 42]);

        self::assertSame(42, $subject->getWebpQuality());
    }

    #[Test]
    public function getWebpQualityDefaultsTo80(): void
    {
        $subject = $this->createSubject([]);

        self::assertSame(80, $subject->getWebpQuality());
    }

    #[Test]
    public function getWebpQualityClampsBelowRangeToOne(): void
    {
        $subject = $this->createSubject(['webpQuality' => -5]);

        self::assertSame(1, $subject->getWebpQuality());
    }

    #[Test]
    public function getWebpQualityClampsAboveRangeToHundred(): void
    {
        $subject = $this->createSubject(['webpQuality' => 500]);

        self::assertSame(100, $subject->getWebpQuality());
    }

    /**
     * @param array<string, mixed> $extConf
     */
    private function createSubject(array $extConf): ExtensionConfiguration
    {
        $coreExtensionConfigurationMock = $this->createMock(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class);
        $coreExtensionConfigurationMock
            ->expects(self::once())
            ->method('get')
            ->with('typo3_image_compression')
            ->willReturn($extConf);

        GeneralUtility::addInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class, $coreExtensionConfigurationMock);

        return new ExtensionConfiguration();
    }
}
