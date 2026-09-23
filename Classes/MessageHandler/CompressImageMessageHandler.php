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

namespace MoveElevator\Typo3ImageCompression\MessageHandler;

use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use MoveElevator\Typo3ImageCompression\Message\CompressImageMessage;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Processing\FileDeletionAspect;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CompressImageMessageHandler.
 *
 * Runs the actual compression for a dispatched {@see CompressImageMessage}.
 * With the default synchronous Messenger transport this executes immediately
 * after dispatch, in the same request; a project that routes the message to
 * an async transport moves it to a `messenger:consume` worker instead.
 *
 * Any processed file (thumbnail, crop) generated between the original event
 * and this handler running would have been built from the uncompressed
 * original, so the derivative cache is invalidated again here, after
 * compression, the same way {@see \MoveElevator\Typo3ImageCompression\Command\CompressImageCommand}
 * does for the CLI batch path.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CompressImageMessageHandler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly CompressorInterface $compressor,
    ) {}

    public function __invoke(CompressImageMessage $message): void
    {
        try {
            $file = $this->resourceFactory->getFileObject($message->fileUid);
        } catch (FileDoesNotExistException) {
            $this->logger?->notice('Skipping compression: file no longer exists', [
                'fileUid' => $message->fileUid,
            ]);

            return;
        }

        if ($file->getStorage()->getUid() !== $message->storageUid) {
            $this->logger?->notice('Skipping compression: file storage changed since dispatch', [
                'fileUid' => $message->fileUid,
                'expectedStorageUid' => $message->storageUid,
                'actualStorageUid' => $file->getStorage()->getUid(),
            ]);

            return;
        }

        $this->compressor->compress($file);

        GeneralUtility::makeInstance(FileDeletionAspect::class)->cleanupProcessedFilesPostFileReplace(
            new AfterFileReplacedEvent($file, ''),
        );
    }
}
