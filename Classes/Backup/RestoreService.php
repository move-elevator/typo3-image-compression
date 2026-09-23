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

namespace MoveElevator\Typo3ImageCompression\Backup;

use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Resource\Processing\FileDeletionAspect;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreService.
 *
 * Single source of truth for "restore a file by UID", used both by the CLI
 * command and the file-list restore action, so the restore/reset/rebuild
 * sequence exists in exactly one place.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class RestoreService implements SingletonInterface
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly BackupService $backupService,
    ) {}

    public function restoreByUid(int $fileUid): bool
    {
        $backupPath = $this->fileRepository->findBackupPathByUid($fileUid);

        if (null === $backupPath) {
            return false;
        }

        try {
            $resourceFile = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return false;
        }

        $absoluteFilePath = urldecode(
            rtrim(Environment::getPublicPath(), '/').'/'.ltrim((string) $resourceFile->getPublicUrl(), '/'),
        );

        if (!$this->backupService->restore($backupPath, $absoluteFilePath)) {
            // Only forget the reference once the backup is confirmed gone; a
            // failed restore with an intact backup (e.g. the target was
            // temporarily unwritable) must stay retryable.
            if (!$this->backupService->backupExists($backupPath)) {
                $this->fileRepository->updateBackupPath($fileUid, '');
            }

            return false;
        }

        $this->fileRepository->updateCompressionStatus($fileUid, false, '', '');
        $this->fileRepository->updateBackupPath($fileUid, '');

        // The restored bytes differ from what FAL last indexed (size, hash);
        // re-index before the cleanup below, which itself reads the file.
        $fileIndexer = GeneralUtility::makeInstance(Indexer::class, $resourceFile->getStorage());
        $fileIndexer->updateIndexEntry($resourceFile);

        $fileDeletionAspect = GeneralUtility::makeInstance(FileDeletionAspect::class);
        $fileDeletionAspect->cleanupProcessedFilesPostFileReplace(new AfterFileReplacedEvent($resourceFile, ''));

        return true;
    }
}
