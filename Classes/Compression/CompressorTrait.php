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

namespace MoveElevator\Typo3ImageCompression\Compression;

use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\{File, ResourceStorage};
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function sprintf;
use function strlen;

/**
 * CompressorTrait.
 *
 * @property ExtensionConfiguration $extensionConfiguration
 * @property FileRepository         $fileRepository
 * @property BackupService          $backupService
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
     * Marks the file as already optimal: the compressed result did not meet
     * the configured minimum saving threshold, so the original was kept.
     */
    protected function markFileAsOptimal(File $file, string $compressInfo): void
    {
        $this->fileRepository->updateCompressionSkipped($file->getUid(), $compressInfo);
    }

    /**
     * Checks whether a compressed result is worth replacing the original with.
     *
     * Compares the saved percentage against the configured minimum saving
     * threshold, so a result that is technically smaller but only by a
     * negligible amount is not treated as a real improvement.
     */
    protected function meetsMinimumSaving(int $originalSize, int $newSize): bool
    {
        if ($originalSize <= 0 || $newSize <= 0) {
            return false;
        }

        return $this->calculateSavedPercent($originalSize, $newSize) >= $this->extensionConfiguration->getMinimumSavingPercent();
    }

    /**
     * Runs $optimize against a temporary copy of $filePath and only replaces
     * the original once the result respects the configured minimum saving
     * threshold.
     *
     * Needed because command-line tools (jpegoptim, ImageMagick, ...) write
     * their result to the same path they were given, overwriting the source
     * before its size could be compared. Operating on a copy first keeps the
     * original untouched until the result is known to be worth keeping.
     *
     * The temporary path keeps the original file's extension (inserting the
     * random token before it) instead of always appending a literal `.tmp`.
     * Tools that infer the output format from the filename, e.g. pngquant's
     * `--ext .png`, would otherwise write their result to a different,
     * never-checked sibling path and leave the temp file itself untouched.
     *
     * @param callable(string $tempPath): bool $optimize Mutates the file at the given temp path in place, returns whether the tool succeeded
     *
     * @return array{originalSize: int, newSize: int, replaced: bool}|null Null when the optimize step itself failed or the temp file could not be created
     */
    protected function compressToTempAndReplace(string $filePath, callable $optimize): ?array
    {
        $originalSize = (int) filesize($filePath);
        $extension = pathinfo($filePath, \PATHINFO_EXTENSION);
        $suffix = '.compress-'.bin2hex(random_bytes(4));
        $tempPath = '' !== $extension
            ? substr($filePath, 0, -(strlen($extension) + 1)).$suffix.'.'.$extension
            : $filePath.$suffix.'.tmp';

        if (!copy($filePath, $tempPath)) {
            return null;
        }

        try {
            if (!$optimize($tempPath)) {
                return null;
            }

            clearstatcache(true, $tempPath);
            $newSize = (int) filesize($tempPath);

            if (!$this->meetsMinimumSaving($originalSize, $newSize)) {
                return ['originalSize' => $originalSize, 'newSize' => $newSize, 'replaced' => false];
            }

            if (!rename($tempPath, $filePath)) {
                return null;
            }

            return ['originalSize' => $originalSize, 'newSize' => $newSize, 'replaced' => true];
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
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
        $savedLabel = $savedPercent > 0 ? sprintf('-%d%%', $savedPercent) : sprintf('%d%%', $savedPercent);
        $originalFormatted = $this->formatFileSize($originalSize);
        $newFormatted = $this->formatFileSize($newSize);

        if (null !== $tool && '' !== $tool) {
            return sprintf(
                '%s (%s): %s -> %s (%s) - %s',
                $provider,
                $tool,
                $originalFormatted,
                $newFormatted,
                $savedLabel,
                $date,
            );
        }

        return sprintf(
            '%s: %s -> %s (%s) - %s',
            $provider,
            $originalFormatted,
            $newFormatted,
            $savedLabel,
            $date,
        );
    }

    /**
     * Builds the info string for a file that was compressed but kept
     * unchanged because the result did not meet the minimum saving
     * threshold.
     *
     * @param string      $provider Provider identifier (e.g. "tinify", "local-tools")
     * @param int         $size     Original (and kept) file size in bytes
     * @param string|null $tool     Optional tool name (e.g. "jpegoptim", "ImageMagick")
     */
    protected function buildSkippedInfo(string $provider, int $size, ?string $tool = null): string
    {
        $date = date('d.m.Y');
        $formatted = $this->formatFileSize($size);

        if (null !== $tool && '' !== $tool) {
            return sprintf('%s (%s): %s already optimal, kept original - %s', $provider, $tool, $formatted, $date);
        }

        return sprintf('%s: %s already optimal, kept original - %s', $provider, $formatted, $date);
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
     * Resolves the absolute filesystem path for a processed file.
     *
     * Returns null when the identifier contains directory traversal
     * sequences, preventing compression tools from writing to files
     * outside the storage's base path.
     */
    protected function resolveProcessedFilePath(ResourceStorage $storage, string $identifier): ?string
    {
        $basePath = (string) ($storage->getConfiguration()['basePath'] ?? '');

        return $this->buildStoragePath(Environment::getPublicPath(), $basePath, $identifier);
    }

    /**
     * Assembles a storage-relative identifier into an absolute path.
     *
     * The identifier is a filesystem-relative path (not URL-encoded) and is
     * validated against traversal sequences before being joined. Returns null
     * for empty or unsafe identifiers.
     */
    protected function buildStoragePath(string $publicPath, string $basePath, string $identifier): ?string
    {
        if ('' === $identifier || !GeneralUtility::validPathStr($identifier)) {
            return null;
        }

        return rtrim($publicPath, '/').'/'.$basePath.$identifier;
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
     * Backs up the original file before compression overwrites it in place,
     * when backup is enabled. Failures are non-fatal: compression proceeds
     * either way, it just isn't restorable afterwards.
     */
    protected function maybeBackupOriginal(File $file, string $filePath): void
    {
        if (!$this->extensionConfiguration->isBackupEnabled()) {
            return;
        }

        $backupPath = $this->backupService->backup($file, $filePath);

        if (null !== $backupPath) {
            $this->fileRepository->updateBackupPath($file->getUid(), $backupPath);
        }
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
