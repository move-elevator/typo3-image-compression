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

namespace MoveElevator\Typo3ImageCompression\Domain\Repository;

use Doctrine\DBAL\{ArrayParameterType, Exception, ParameterType};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Model\{File, FileStorage};
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\{QueryInterface, QueryResultInterface, Repository};

/**
 * FileRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 *
 * @extends Repository<File>
 */
class FileRepository extends Repository
{
    protected $objectType = File::class;

    private ConnectionPool $connectionPool;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function injectConnectionPool(ConnectionPool $connectionPool): void
    {
        $this->connectionPool = $connectionPool;
    }

    public function createQuery(): QueryInterface
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query;
    }

    /**
     * @param string[]      $excludeFolders
     * @param string[]|null $mimeTypes      Overrides the configured `mimeTypes` setting, e.g.
     *                                      with a provider's effective (zero-configuration
     *                                      extended) allowlist. Defaults to the configured setting.
     *
     * @return QueryResultInterface<int, File>
     *
     * @throws InvalidQueryException
     */
    public function findAllNonCompressedInStorageWithLimit(
        FileStorage $storage,
        int $limit = 100,
        array $excludeFolders = [],
        ?array $mimeTypes = null,
        ?string $folder = null,
    ): QueryResultInterface {
        $query = $this->createQuery();

        $excludeFoldersConstraints = [];
        foreach ($excludeFolders as $excludeFolder) {
            $excludeFoldersConstraints[] = $query->logicalNot(
                $query->like('identifier', $this->escapeLikeValue($excludeFolder).'%'),
            );
        }

        $query->matching(
            $query->logicalAnd(
                ...array_merge(
                    [
                        $query->equals('storage', $storage),
                        $query->equals('compressed', false),
                        $query->equals('compressSkipped', false),
                        $query->equals('missing', false),
                        $query->logicalOr(
                            $query->equals('compress_error', null),
                            $query->equals('compress_error', ''),
                        ),
                        $query->in(
                            'mime_type',
                            $mimeTypes ?? $this->extensionConfiguration->getMimeTypes(),
                        ),
                    ],
                    $excludeFoldersConstraints,
                    $this->buildFolderConstraint($query, $folder),
                ),
            ),
        );
        $query->setLimit($limit);

        return $query->execute();
    }

    /**
     * Finds compression status data for a file by its UID.
     *
     * @return array{compressed: bool, compress_skipped: bool, compress_error: string, compress_info: string, compress_provider: string, compress_tool: string, compress_original_size: int, compress_size: int, compress_tstamp: int}|null
     *
     * @throws Exception
     */
    public function findCompressionStatusByUid(int $fileUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $row = $queryBuilder
            ->select(
                'compressed',
                'compress_skipped',
                'compress_error',
                'compress_info',
                'compress_provider',
                'compress_tool',
                'compress_original_size',
                'compress_size',
                'compress_tstamp',
            )
            ->from('sys_file')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();

        if (false === $row) {
            return null;
        }

        return [
            'compressed' => (bool) $row['compressed'],
            'compress_skipped' => (bool) $row['compress_skipped'],
            'compress_error' => (string) $row['compress_error'],
            'compress_info' => (string) $row['compress_info'],
            'compress_provider' => (string) $row['compress_provider'],
            'compress_tool' => (string) $row['compress_tool'],
            'compress_original_size' => (int) $row['compress_original_size'],
            'compress_size' => (int) $row['compress_size'],
            'compress_tstamp' => (int) $row['compress_tstamp'],
        ];
    }

    /**
     * Finds all files with compression errors in a storage.
     *
     * @param string[]      $excludeFolders
     * @param string[]|null $mimeTypes      Overrides the configured `mimeTypes` setting, e.g.
     *                                      with a provider's effective (zero-configuration
     *                                      extended) allowlist. Defaults to the configured setting.
     *
     * @return QueryResultInterface<int, File>
     *
     * @throws InvalidQueryException
     */
    public function findAllWithErrorsInStorageWithLimit(
        FileStorage $storage,
        int $limit = 100,
        array $excludeFolders = [],
        ?array $mimeTypes = null,
        ?string $folder = null,
    ): QueryResultInterface {
        $query = $this->createQuery();

        $excludeFoldersConstraints = [];
        foreach ($excludeFolders as $excludeFolder) {
            $excludeFoldersConstraints[] = $query->logicalNot(
                $query->like('identifier', $this->escapeLikeValue($excludeFolder).'%'),
            );
        }

        $query->matching(
            $query->logicalAnd(
                ...array_merge(
                    [
                        $query->equals('storage', $storage),
                        $query->equals('missing', false),
                        $query->logicalNot(
                            $query->logicalOr(
                                $query->equals('compress_error', null),
                                $query->equals('compress_error', ''),
                            ),
                        ),
                        $query->in(
                            'mime_type',
                            $mimeTypes ?? $this->extensionConfiguration->getMimeTypes(),
                        ),
                    ],
                    $excludeFoldersConstraints,
                    $this->buildFolderConstraint($query, $folder),
                ),
            ),
        );
        $query->setLimit($limit);

        return $query->execute();
    }

    /**
     * Updates the compression status for a file using DBAL.
     *
     * @param string $provider     Provider identifier (e.g. "tinify", "local-tools"), empty on failure/reset
     * @param string $tool         Tool name (e.g. "jpegoptim", "ImageMagick"), empty when not applicable
     * @param int    $originalSize Original file size in bytes, 0 on failure/reset
     * @param int    $newSize      New file size in bytes, 0 on failure/reset
     */
    public function updateCompressionStatus(
        int $fileUid,
        bool $compressed,
        string $compressError = '',
        string $provider = '',
        string $tool = '',
        int $originalSize = 0,
        int $newSize = 0,
    ): void {
        $connection = $this->connectionPool->getConnectionForTable('sys_file');

        $connection->update(
            'sys_file',
            [
                'compressed' => $compressed ? 1 : 0,
                'compress_skipped' => 0,
                'compress_error' => $compressError,
                'compress_provider' => $provider,
                'compress_tool' => $tool,
                'compress_original_size' => $originalSize,
                'compress_size' => $newSize,
                'compress_tstamp' => $compressed ? time() : 0,
            ],
            ['uid' => $fileUid],
        );
    }

    /**
     * Stores the relative backup path for a file using DBAL.
     */
    public function updateBackupPath(int $fileUid, string $backupPath): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file');

        $connection->update(
            'sys_file',
            ['backup_path' => $backupPath],
            ['uid' => $fileUid],
        );
    }

    /**
     * Marks a file as already optimal: the compressed result did not meet
     * the configured minimum saving threshold, so the original was kept.
     *
     * Distinct from `updateCompressionStatus(..., compressed: false, ...)`,
     * which means "not yet processed" and would otherwise cause the file to
     * be retried on every batch run.
     */
    public function updateCompressionSkipped(int $fileUid, string $compressInfo): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file');

        $connection->update(
            'sys_file',
            [
                'compressed' => 0,
                'compress_skipped' => 1,
                'compress_error' => '',
                'compress_info' => $compressInfo,
            ],
            ['uid' => $fileUid],
        );
    }

    /**
     * Clears backup_path on every sys_file row still pointing at a given
     * relative backup path, so pruning a backup file doesn't leave the file
     * list offering to restore from a path that no longer exists.
     */
    public function clearBackupPathByRelativePath(string $backupPath): void
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file');

        $connection->update(
            'sys_file',
            ['backup_path' => ''],
            ['backup_path' => $backupPath],
        );
    }

    /**
     * Returns the relative backup path for a file, or null if none is set.
     *
     * @throws Exception
     */
    public function findBackupPathByUid(int $fileUid): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $backupPath = $queryBuilder
            ->select('backup_path')
            ->from('sys_file')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();

        if (false === $backupPath || '' === $backupPath) {
            return null;
        }

        return (string) $backupPath;
    }

    /**
     * @return QueryResultInterface<int, File>
     *
     * @throws InvalidQueryException
     */
    public function findAllWithBackup(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->logicalNot($query->equals('backupPath', null)),
                $query->logicalNot($query->equals('backupPath', '')),
            ),
        );

        return $query->execute();
    }

    /**
     * Returns compression statistics for files with given mime types.
     *
     * @param string[] $mimeTypes
     *
     * @return array{compressed: int, not_compressed: int, errors: int}
     */
    public function getCompressionStatistics(array $mimeTypes): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $result = $queryBuilder
            ->selectLiteral(
                'SUM(CASE WHEN compressed = 1 OR compress_skipped = 1 THEN 1 ELSE 0 END) AS compressed',
                'SUM(CASE WHEN compressed = 0 AND compress_skipped = 0 AND (compress_error IS NULL OR compress_error = \'\') THEN 1 ELSE 0 END) AS not_compressed',
                'SUM(CASE WHEN compress_error IS NOT NULL AND compress_error != \'\' THEN 1 ELSE 0 END) AS errors',
            )
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->in(
                    'mime_type',
                    $queryBuilder->createNamedParameter($mimeTypes, ArrayParameterType::STRING),
                ),
                $queryBuilder->expr()->eq('missing', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return [
            'compressed' => (int) ($result['compressed'] ?? 0),
            'not_compressed' => (int) ($result['not_compressed'] ?? 0),
            'errors' => (int) ($result['errors'] ?? 0),
        ];
    }

    /**
     * Returns the total bytes saved across all successfully compressed files.
     */
    public function getTotalBytesSaved(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $result = $queryBuilder
            ->selectLiteral('SUM(compress_original_size - compress_size) AS saved')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('compressed', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) ($result ?? 0);
    }

    /**
     * @param QueryInterface<File> $query
     *
     * @return list<ConstraintInterface>
     */
    private function buildFolderConstraint(QueryInterface $query, ?string $folder): array
    {
        if (null === $folder || '' === $folder) {
            return [];
        }

        return [$query->like('identifier', $this->escapeLikeValue($folder).'%')];
    }

    /**
     * Escapes LIKE metacharacters (`%`, `_`) and the escape character itself
     * in a value that is about to be used as a LIKE prefix, so folder/path
     * values containing these characters are matched literally instead of
     * as wildcards.
     */
    private function escapeLikeValue(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
