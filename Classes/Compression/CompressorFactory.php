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

namespace MoveElevator\Typo3ImageCompression\Compression;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use Psr\Container\ContainerInterface;

/**
 * CompressorFactory.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class CompressorFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function create(): CompressorInterface
    {
        $provider = $this->extensionConfiguration->getProvider();

        return match ($provider) {
            ExtensionConfiguration::PROVIDER_LOCAL_TOOLS => $this->container->get(LocalToolsCompressor::class),
            ExtensionConfiguration::PROVIDER_LOCAL_BASIC => $this->container->get(LocalBasicCompressor::class),
            default => $this->container->get(TinifyCompressor::class),
        };
    }
}
