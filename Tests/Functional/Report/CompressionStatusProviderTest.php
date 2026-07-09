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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Report;

use MoveElevator\Typo3ImageCompression\Report\CompressionStatusProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CompressionStatusProviderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionStatusProvider::class)]
final class CompressionStatusProviderTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['reports'];
    protected array $testExtensionsToLoad = ['move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function getLabelReturnsExtensionKey(): void
    {
        $subject = $this->get(CompressionStatusProvider::class);

        self::assertSame('typo3_image_compression', $subject->getLabel());
    }

    #[Test]
    public function getStatusReturnsEmptyArrayWhenStatusReportIsDisabled(): void
    {
        $this->get(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            ['showStatusReport' => false],
        );

        $subject = $this->get(CompressionStatusProvider::class);

        self::assertSame([], $subject->getStatus());
    }

    #[Test]
    public function getStatusReturnsProviderAndStatisticsButNotApiUsageWhenApiKeyIsEmpty(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file_and_processedfile.csv');

        $this->get(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            [
                'showStatusReport' => true,
                'apiKey' => '',
                'mimeTypes' => 'image/avif,image/jpeg,image/png,image/webp',
            ],
        );

        $subject = $this->get(CompressionStatusProvider::class);
        $statuses = $subject->getStatus();

        self::assertArrayHasKey('provider', $statuses);
        self::assertArrayHasKey('statistics', $statuses);
        self::assertArrayNotHasKey('apiUsage', $statuses);

        self::assertSame('2 / 6', $statuses['statistics']->getValue());
        self::assertStringContainsString('Original Files', $statuses['statistics']->getMessage());
    }
}
