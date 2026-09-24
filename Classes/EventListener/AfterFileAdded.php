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

namespace MoveElevator\Typo3ImageCompression\EventListener;

use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\File;

/**
 * AfterFileAdded.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class AfterFileAdded
{
    public function __construct(private MessageBusInterface $messageBus) {}

    public function __invoke(AfterFileAddedEvent $event): AfterFileAddedEvent
    {
        $file = $event->getFile();

        if ($file instanceof File) {
            $this->messageBus->dispatch(new CompressImageMessage($file->getUid(), $file->getStorage()->getUid()));
        }

        return $event;
    }
}
