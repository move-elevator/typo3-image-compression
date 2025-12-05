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

use function sprintf;

/**
 * CompressorTrait.
 *
 * @property ExtensionConfiguration $extensionConfiguration
 * @property FileRepository         $fileRepository
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
     *
     * @param string $compressInfo Compression info (e.g. "tinify: -45% (2025-12-04)")
     */
    protected function markFileAsCompressed(File $file, string $compressInfo = ''): void
    {
        $this->fileRepository->updateCompressionStatus($file->getUid(), true, '', $compressInfo);
    }

    /**
     * Builds the compression info string.
     *
     * @param string      $provider     Provider identifier (e.g. "tinify", "local-tools")
     * @param int         $originalSize Original file size in bytes
     * @param int         $newSize      New file size in bytes
     * @param string|null $tool         Optional tool name (e.g. "jpegoptim", "ImageMagick")
     */
    protected function buildCompressInfo(string $provider, int $originalSize, int $newSize, ?string $tool = null): string
    {
        $date = date('d.m.Y');
        $savedPercent = $this->calculateSavedPercent($originalSize, $newSize);
        $originalFormatted = $this->formatFileSize($originalSize);
        $newFormatted = $this->formatFileSize($newSize);

        if (null !== $tool && '' !== $tool) {
            return sprintf(
                '%s (%s): %s -> %s (-%d%%) - %s',
                $provider,
                $tool,
                $originalFormatted,
                $newFormatted,
                $savedPercent,
                $date,
            );
        }

        return sprintf(
            '%s: %s -> %s (-%d%%) - %s',
            $provider,
            $originalFormatted,
            $newFormatted,
            $savedPercent,
            $date,
        );
    }

    /**
     * Formats file size in human-readable format.
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return sprintf('%.1f MB', $bytes / 1048576);
        }

        if ($bytes >= 1024) {
            return sprintf('%.0f KB', $bytes / 1024);
        }

        return sprintf('%d B', $bytes);
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
