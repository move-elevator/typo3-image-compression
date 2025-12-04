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

namespace MoveElevator\Typo3ImageCompression\EventListener;

use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Extbase\Persistence\Exception\{IllegalObjectTypeException, UnknownObjectException};

/**
 * AfterFileReplaced.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class AfterFileReplaced
{
    public function __construct(private CompressorInterface $compressor) {}

    /**
     * @throws Exception
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     */
    public function __invoke(AfterFileReplacedEvent $event): AfterFileReplacedEvent
    {
        $this->compressor->compress($event->getFile());

        return $event;
    }
}
