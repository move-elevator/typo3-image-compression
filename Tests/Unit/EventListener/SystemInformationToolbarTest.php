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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\EventListener;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\EventListener\SystemInformationToolbar;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Backend\Event\SystemInformationToolbarCollectorEvent;
use TYPO3\CMS\Backend\Backend\ToolbarItems\SystemInformationToolbarItem;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Resource\{File, FileInterface};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SystemInformationToolbarTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(SystemInformationToolbar::class)]
final class SystemInformationToolbarTest extends TestCase
{
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;

    protected function setUp(): void
    {
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('Compressed images');
        $GLOBALS['LANG'] = $languageServiceMock;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function invokeDoesNothingWhenSystemInformationToolbarIsDisabled(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('isSystemInformationToolbar')
            ->willReturn(false);
        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        $compressorMock = $this->createMock(CompressorInterface::class);
        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock->expects(self::never())->method('addSystemInformation');

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeDoesNothingWhenApiKeyIsEmpty(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getApiKey')
            ->willReturn('');

        $compressorMock = $this->createMock(CompressorInterface::class);
        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock->expects(self::never())->method('addSystemInformation');

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeDoesNothingWhenCompressorIsNotQuotaAware(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('some-api-key');

        $compressorMock = $this->createMock(CompressorInterface::class);
        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock->expects(self::never())->method('addSystemInformation');

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeDoesNothingWhenCompressionCountIsNull(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('some-api-key');

        $compressorMock = $this->createQuotaAwareCompressor();
        $compressorMock->expects(self::once())->method('getCompressionCount')->willReturn(null);
        $compressorMock->expects(self::never())->method('getQuotaLimit');

        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock->expects(self::never())->method('addSystemInformation');

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeAddsSystemInformationOnHappyPath(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('some-api-key');

        $compressorMock = $this->createQuotaAwareCompressor();
        $compressorMock->method('getCompressionCount')->willReturn(250);
        $compressorMock->method('getQuotaLimit')->willReturn(500);

        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock
            ->expects(self::once())
            ->method('addSystemInformation')
            ->with('Compressed images', '250 / 500', 'actions-image', self::anything());

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeFormatsUnlimitedQuotaAsInfinity(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('some-api-key');

        $compressorMock = $this->createQuotaAwareCompressor();
        $compressorMock->method('getCompressionCount')->willReturn(250);
        $compressorMock->method('getQuotaLimit')->willReturn(null);

        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock
            ->expects(self::once())
            ->method('addSystemInformation')
            ->with('Compressed images', '250 / ∞', 'actions-image', self::anything());

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    #[Test]
    public function invokeFormatsZeroCompressionCountAsQuestionMark(): void
    {
        $this->extensionConfigurationMock->method('isSystemInformationToolbar')->willReturn(true);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('some-api-key');

        $compressorMock = $this->createQuotaAwareCompressor();
        $compressorMock->method('getCompressionCount')->willReturn(0);
        $compressorMock->method('getQuotaLimit')->willReturn(500);

        [$event, $toolbarItemMock] = $this->createEvent();
        $toolbarItemMock
            ->expects(self::once())
            ->method('addSystemInformation')
            ->with('Compressed images', '?', 'actions-image', self::anything());

        $subject = new SystemInformationToolbar($this->extensionConfigurationMock, $compressorMock);
        $subject($event);
    }

    /**
     * @return array{0: SystemInformationToolbarCollectorEvent, 1: SystemInformationToolbarItem&MockObject}
     */
    private function createEvent(): array
    {
        $toolbarItemMock = $this->createMock(SystemInformationToolbarItem::class);

        return [new SystemInformationToolbarCollectorEvent($toolbarItemMock), $toolbarItemMock];
    }

    private function createQuotaAwareCompressor(): CompressorInterface&QuotaAwareInterface&MockObject
    {
        return $this->createMock(QuotaAwareCompressorTestDouble::class);
    }
}

/**
 * QuotaAwareCompressorTestDouble.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
abstract class QuotaAwareCompressorTestDouble implements CompressorInterface, QuotaAwareInterface
{
    public function compress(File|FileInterface $file): void {}

    public function compressProcessedFiles(array $files): void {}

    public function getProviderIdentifier(): string
    {
        return 'test-double';
    }

    abstract public function getCompressionCount(): ?int;

    abstract public function getQuotaLimit(): ?int;
}
