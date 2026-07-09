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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Utility;

use MoveElevator\Typo3ImageCompression\Utility\ViewUtility;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ViewUtilityTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(ViewUtility::class)]
final class ViewUtilityTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['reports'];
    protected array $testExtensionsToLoad = ['move-elevator/typo3-image-compression'];

    #[Test]
    public function renderTemplateRendersCompressionStatisticsTemplateWithGivenVariables(): void
    {
        $html = ViewUtility::renderTemplate('CompressionStatistics', 'Report/', [
            'original' => [
                'statistics' => ['compressed' => 3, 'not_compressed' => 1, 'errors' => 0],
                'total' => 4,
                'percent' => 75,
                'color' => 'green',
            ],
            'processed' => [
                'statistics' => ['compressed' => 0, 'not_compressed' => 0, 'errors' => 0],
                'total' => 0,
                'percent' => 0,
                'color' => 'green',
            ],
            'hasErrors' => false,
            'labels' => [
                'original' => 'Original Files',
                'processed' => 'Processed Files',
                'compressed' => 'Compressed',
                'notCompressed' => 'Not compressed',
                'errors' => 'Errors',
                'errorsMessage' => 'Some files failed to compress.',
            ],
        ]);

        self::assertStringContainsString('Original Files', $html);
        self::assertStringContainsString('<strong>3</strong>', $html);
        self::assertStringNotContainsString('Some files failed to compress.', $html);
    }

    #[Test]
    public function renderTemplateRendersCompressionInfoTemplateWithGivenVariables(): void
    {
        $html = ViewUtility::renderTemplate('CompressionInfo', 'Form/', [
            'status' => 'compressed',
            'statusLabel' => 'Compressed',
            'label' => 'Image Compression',
            'message' => 'Saved 42%',
        ]);

        self::assertStringContainsString('Image Compression', $html);
        self::assertStringContainsString('Compressed', $html);
        self::assertStringContainsString('Saved 42%', $html);
    }
}
