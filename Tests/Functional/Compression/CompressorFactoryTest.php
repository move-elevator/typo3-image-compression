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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Compression;

use MoveElevator\Typo3ImageCompression\Compression\{CompressorChain, CompressorInterface, TinifyCompressor};
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CompressorFactoryTest.
 *
 * Exercises the real `Services.yaml` factory wiring: a single configured
 * provider resolves directly, several resolve to a real {@see CompressorChain}.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversNothing]
final class CompressorFactoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    #[Test]
    public function containerResolvesTheSoleConfiguredProviderDirectly(): void
    {
        $this->get(CoreExtensionConfiguration::class)->set('typo3_image_compression', ['provider' => 'tinify']);

        self::assertInstanceOf(TinifyCompressor::class, $this->get(CompressorInterface::class));
    }

    #[Test]
    public function containerResolvesMultipleConfiguredProvidersAsAChain(): void
    {
        $this->get(CoreExtensionConfiguration::class)->set('typo3_image_compression', ['provider' => 'tinify,local-tools']);

        self::assertInstanceOf(CompressorChain::class, $this->get(CompressorInterface::class));
    }
}
