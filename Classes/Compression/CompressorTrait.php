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
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * CompressorTrait.
 *
 * @property ExtensionConfiguration $extensionConfiguration
 * @property FileRepository         $fileRepository
 * @property PersistenceManager     $persistenceManager
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
trait CompressorTrait
{
    /**
     * Checks if the file is located in an excluded folder.
     *
     * Excluded folders are configured in the extension settings and
     * prevent compression of files within those directories.
     */
    protected function isFileInExcludeFolder(File $file): bool
    {
        $excludeFolders = $this->extensionConfiguration->getExcludeFolders();
        $identifier = $file->getIdentifier();

        foreach ($excludeFolders as $excludeFolder) {
            if (str_starts_with($identifier, $excludeFolder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the absolute filesystem path to the file.
     *
     * Combines TYPO3's public path with the file's public URL,
     * properly handling URL encoding.
     */
    protected function getAbsoluteFilePath(File $file): string
    {
        return urldecode(
            rtrim(Environment::getPublicPath(), '/').'/'.ltrim((string) $file->getPublicUrl(), '/'),
        );
    }

    /**
     * Marks the file as compressed in the database.
     *
     * Updates the sys_file record to indicate successful compression
     * and clears any previous compression errors.
     */
    protected function markFileAsCompressed(File $file): void
    {
        /** @var \MoveElevator\Typo3ImageCompression\Domain\Model\File|null $extbaseFileObject */
        $extbaseFileObject = $this->fileRepository->findByUid($file->getUid());

        if (null === $extbaseFileObject) {
            return;
        }

        $extbaseFileObject->setCompressed(true);
        $extbaseFileObject->resetCompressError();
        $this->fileRepository->update($extbaseFileObject);
        $this->persistenceManager->persistAll();
    }

    /**
     * Updates the FAL index entry for the file.
     *
     * Triggers re-indexing to update file metadata (size, hash, etc.)
     * after compression has modified the file on disk.
     */
    protected function updateFileInformation(File $file): void
    {
        $storage = $file->getStorage();
        $fileIndexer = GeneralUtility::makeInstance(Indexer::class, $storage);
        $fileIndexer->updateIndexEntry($file);
    }

    /**
     * Calculates the percentage of file size saved through compression.
     *
     * @param int $originalSize Original file size in bytes
     * @param int $newSize      Compressed file size in bytes
     *
     * @return int Percentage saved (0-100), or 0 if sizes are invalid
     */
    protected function calculateSavedPercent(int $originalSize, int $newSize): int
    {
        if ($originalSize <= 0 || $newSize <= 0) {
            return 0;
        }

        return (int) (100 - (($newSize / $originalSize) * 100));
    }
}
