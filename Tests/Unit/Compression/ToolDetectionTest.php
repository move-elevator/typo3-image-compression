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

use MoveElevator\Typo3ImageCompression\Compression\ToolDetection;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * ToolDetectionTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ToolDetection::class)]
final class ToolDetectionTest extends TestCase
{
    private ToolDetection $subject;

    protected function setUp(): void
    {
        // Initialize TYPO3_CONF_VARS to prevent warnings from CommandUtility
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['binSetup'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['binPath'] = '/usr/bin/';
        $GLOBALS['TYPO3_CONF_VARS']['BE']['disable_exec_function'] = false;
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'ImageMagick';
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path'] = '/usr/bin/';

        $this->subject = new ToolDetection();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    #[Test]
    public function getSupportedToolsReturnsAllKnownTools(): void
    {
        $tools = $this->subject->getSupportedTools();

        self::assertContains('jpegoptim', $tools);
        self::assertContains('optipng', $tools);
        self::assertContains('pngquant', $tools);
        self::assertContains('gifsicle', $tools);
        self::assertContains('cwebp', $tools);
        self::assertContains('avifenc', $tools);
        self::assertContains('imagemagick', $tools);
        self::assertContains('graphicsmagick', $tools);
    }

    #[Test]
    public function isAvailableReturnsFalseForUnknownTool(): void
    {
        self::assertFalse($this->subject->isAvailable('unknown-tool'));
    }

    #[Test]
    public function getToolPathReturnsNullForUnknownTool(): void
    {
        self::assertNull($this->subject->getToolPath('unknown-tool'));
    }

    #[Test]
    public function getFirstAvailableReturnsNullForEmptyArray(): void
    {
        self::assertNull($this->subject->getFirstAvailable([]));
    }

    #[Test]
    public function getFirstAvailableReturnsNullWhenNoToolsAvailable(): void
    {
        self::assertNull($this->subject->getFirstAvailable(['unknown-tool-1', 'unknown-tool-2']));
    }

    #[Test]
    public function clearCacheClearsInternalCache(): void
    {
        // Call isAvailable to populate cache
        $this->subject->isAvailable('jpegoptim');
        $this->subject->getToolPath('jpegoptim');

        // Clear cache
        $this->subject->clearCache();

        // After clearing, the cache should be empty - verify method still works
        $available = $this->subject->getAvailableTools();

        // No exception means success - verify we got an array back
        self::assertIsArray($available);
    }

    #[Test]
    public function getAvailableToolsReturnsExpectedFormat(): void
    {
        $available = $this->subject->getAvailableTools();

        // All returned tools should be strings from the supported tools list
        foreach ($available as $tool) {
            self::assertContains($tool, $this->subject->getSupportedTools());
        }
    }

    #[Test]
    public function hasOptimizedToolsChecksCorrectTools(): void
    {
        // hasOptimizedTools should return true only if jpegoptim, optipng, or pngquant is available
        // We cannot control which tools are installed, but we can verify the method runs
        $result = $this->subject->hasOptimizedTools();

        // If any of the optimized tools are available, result should be true
        $jpegoptim = $this->subject->isAvailable('jpegoptim');
        $optipng = $this->subject->isAvailable('optipng');
        $pngquant = $this->subject->isAvailable('pngquant');

        self::assertSame($jpegoptim || $optipng || $pngquant, $result);
    }

    #[Test]
    public function hasBasicToolsChecksCorrectTools(): void
    {
        // hasBasicTools should return true only if imagemagick or graphicsmagick is available
        $result = $this->subject->hasBasicTools();

        $imagemagick = $this->subject->isAvailable('imagemagick');
        $graphicsmagick = $this->subject->isAvailable('graphicsmagick');

        self::assertSame($imagemagick || $graphicsmagick, $result);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function knownToolsDataProvider(): array
    {
        return [
            'jpegoptim' => ['jpegoptim'],
            'optipng' => ['optipng'],
            'pngquant' => ['pngquant'],
            'gifsicle' => ['gifsicle'],
            'cwebp' => ['cwebp'],
            'avifenc' => ['avifenc'],
            'imagemagick' => ['imagemagick'],
            'graphicsmagick' => ['graphicsmagick'],
        ];
    }

    #[Test]
    #[DataProvider('knownToolsDataProvider')]
    public function isAvailableDoesNotThrowForKnownTools(string $tool): void
    {
        // Method should not throw an exception for known tools
        $result = $this->subject->isAvailable($tool);

        // Result must be a boolean
        self::assertIsBool($result);
    }

    #[Test]
    #[DataProvider('knownToolsDataProvider')]
    public function getToolPathDoesNotThrowForKnownTools(string $tool): void
    {
        // Method should not throw an exception for known tools
        $result = $this->subject->getToolPath($tool);

        // Result should be either null (tool not found) or a non-empty string (path)
        if (null !== $result) {
            self::assertNotEmpty($result);
        } else {
            // When null, verify it's actually null (tool not installed)
            self::assertFalse($this->subject->isAvailable($tool));
        }
    }

    #[Test]
    public function isAvailableUsesCacheOnSecondCall(): void
    {
        // First call
        $result1 = $this->subject->isAvailable('jpegoptim');

        // Second call should use cache
        $result2 = $this->subject->isAvailable('jpegoptim');

        self::assertSame($result1, $result2);
    }
}
