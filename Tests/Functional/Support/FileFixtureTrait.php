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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Support;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function dirname;

/**
 * FileFixtureTrait.
 *
 * A single local storage at fileadmin/test/, backed by a real file and (for
 * restore scenarios) a real backup file on disk, plus the matching sys_file
 * row(s). Shared by every functional test around the restore feature
 * (Controller, ContextMenu, Resource, and the CLI Command), which otherwise
 * carried byte-identical copies of the same four helpers.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
trait FileFixtureTrait
{
    private function importBackendUser(bool $isAdmin): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('be_users');
        $connection->insert('be_users', [
            'pid' => 0,
            'username' => $isAdmin ? 'admin' : 'restricted',
            'password' => '',
            'admin' => $isAdmin ? 1 : 0,
        ]);

        return (int) $connection->lastInsertId('be_users');
    }

    private function createLocalTestStorage(): int
    {
        GeneralUtility::mkdir_deep(Environment::getPublicPath().'/fileadmin/test/');

        return $this->get(StorageRepository::class)->createLocalStorage(
            'Test storage',
            'fileadmin/test/',
            'relative',
        );
    }

    private function writeRealFile(string $fileName, string $contents): void
    {
        GeneralUtility::writeFile(Environment::getPublicPath().'/fileadmin/test/'.$fileName, $contents);
    }

    private function writeBackupFile(string $relativePath, string $contents): void
    {
        $absolutePath = Environment::getVarPath().'/image_compression/backup/'.$relativePath;
        GeneralUtility::mkdir_deep(dirname($absolutePath));
        GeneralUtility::writeFile($absolutePath, $contents);
    }

    private function importSysFileRow(int $storageUid, string $identifier, string $name, string $backupPath = ''): int
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file');
        $connection->insert('sys_file', [
            'pid' => 0,
            'storage' => $storageUid,
            'identifier' => $identifier,
            'identifier_hash' => sha1($identifier),
            'folder_hash' => sha1(dirname($identifier)),
            'name' => $name,
            'mime_type' => 'image/jpeg',
            'missing' => 0,
            'compressed' => '' === $backupPath ? 0 : 1,
            'backup_path' => $backupPath,
        ]);

        return (int) $connection->lastInsertId('sys_file');
    }
}
