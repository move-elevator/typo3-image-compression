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

use FilesystemIterator;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function dirname;
use function sprintf;

/**
 * BackupService.
 *
 * Keeps a copy of the original file outside FAL, so it is not indexed or
 * shown in the file list, before a compressor overwrites it in place.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class BackupService implements LoggerAwareInterface, SingletonInterface
{
    use LoggerAwareTrait;

    private const BACKUP_DIR_NAME = 'image_compression/backup';

    /**
     * Copies the file at $absoluteFilePath into the backup directory.
     * Returns the backup path relative to the backup base directory (to be
     * stored on the sys_file record), or null if the copy failed.
     */
    public function backup(File $file, string $absoluteFilePath): ?string
    {
        if (!file_exists($absoluteFilePath)) {
            return null;
        }

        $relativePath = $this->buildRelativeBackupPath($file, $absoluteFilePath);
        $absoluteBackupPath = $this->getBackupBasePath().'/'.$relativePath;
        $backupDirectory = dirname($absoluteBackupPath);

        // mkdir_deep() itself throws on failure instead of returning false.
        try {
            GeneralUtility::mkdir_deep($backupDirectory);
        } catch (RuntimeException) {
            // handled below via the is_dir() check
        }

        if (!is_dir($backupDirectory)) {
            $this->logger?->warning('Could not create backup directory', ['path' => $backupDirectory]);

            return null;
        }

        if (!copy($absoluteFilePath, $absoluteBackupPath)) {
            $this->logger?->warning('Could not write backup file', ['source' => $absoluteFilePath, 'target' => $absoluteBackupPath]);

            return null;
        }

        return $relativePath;
    }

    /**
     * Restores the backup identified by $backupRelativePath onto $absoluteFilePath.
     */
    public function restore(string $backupRelativePath, string $absoluteFilePath): bool
    {
        $absoluteBackupPath = $this->getBackupBasePath().'/'.$backupRelativePath;

        if (!file_exists($absoluteBackupPath)) {
            $this->logger?->warning('Backup file not found', ['path' => $absoluteBackupPath]);

            return false;
        }

        if (!copy($absoluteBackupPath, $absoluteFilePath)) {
            $this->logger?->warning('Could not restore backup file', ['source' => $absoluteBackupPath, 'target' => $absoluteFilePath]);

            return false;
        }

        return true;
    }

    /**
     * Deletes backup files older than $retentionDays. Returns the number of deleted files.
     */
    public function prune(int $retentionDays, bool $dryRun = false): int
    {
        $basePath = $this->getBackupBasePath();

        if (!is_dir($basePath)) {
            return 0;
        }

        $cutoff = time() - ($retentionDays * 86400);
        $deleted = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || $fileInfo->getMTime() >= $cutoff) {
                continue;
            }

            if (!$dryRun && !unlink($fileInfo->getPathname())) {
                $this->logger?->warning('Could not delete backup file during prune', ['path' => $fileInfo->getPathname()]);

                continue;
            }

            ++$deleted;
        }

        return $deleted;
    }

    private function getBackupBasePath(): string
    {
        return rtrim(Environment::getVarPath(), '/').'/'.self::BACKUP_DIR_NAME;
    }

    private function buildRelativeBackupPath(File $file, string $absoluteFilePath): string
    {
        $storageUid = $file->getStorage()->getUid();
        $hash = hash_file('sha256', $absoluteFilePath) ?: hash('sha256', $absoluteFilePath.microtime());
        $extension = pathinfo($absoluteFilePath, \PATHINFO_EXTENSION);

        return sprintf('%d/%s%s', $storageUid, $hash, '' !== $extension ? '.'.$extension : '');
    }
}
