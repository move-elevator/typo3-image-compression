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
     * @param string[] $excludeFolders
     *
     * @return QueryResultInterface<int, File>
     *
     * @throws InvalidQueryException
     */
    public function findAllNonCompressedInStorageWithLimit(
        FileStorage $storage,
        int $limit = 100,
        array $excludeFolders = [],
    ): QueryResultInterface {
        $query = $this->createQuery();

        $excludeFoldersConstraints = [];
        foreach ($excludeFolders as $excludeFolder) {
            $excludeFoldersConstraints[] = $query->logicalNot(
                $query->like('identifier', $excludeFolder.'%'),
            );
        }

        $query->matching(
            $query->logicalAnd(
                ...array_merge(
                    [
                        $query->equals('storage', $storage),
                        $query->equals('compressed', false),
                        $query->equals('missing', false),
                        $query->logicalOr(
                            $query->equals('compress_error', null),
                            $query->equals('compress_error', ''),
                        ),
                        $query->in(
                            'mime_type',
                            $this->extensionConfiguration->getMimeTypes(),
                        ),
                    ],
                    $excludeFoldersConstraints,
                ),
            ),
        );
        $query->setLimit($limit);

        return $query->execute();
    }

    /**
     * Finds compression status data for a file by its UID.
     *
     * @return array{compressed: bool, compress_error: string, compress_provider: string, compress_tool: string, compress_original_size: int, compress_size: int, compress_tstamp: int}|null
     *
     * @throws Exception
     */
    public function findCompressionStatusByUid(int $fileUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $row = $queryBuilder
            ->select(
                'compressed',
                'compress_error',
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
            'compress_error' => (string) $row['compress_error'],
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
     * @param string[] $excludeFolders
     *
     * @return QueryResultInterface<int, File>
     *
     * @throws InvalidQueryException
     */
    public function findAllWithErrorsInStorageWithLimit(
        FileStorage $storage,
        int $limit = 100,
        array $excludeFolders = [],
    ): QueryResultInterface {
        $query = $this->createQuery();

        $excludeFoldersConstraints = [];
        foreach ($excludeFolders as $excludeFolder) {
            $excludeFoldersConstraints[] = $query->logicalNot(
                $query->like('identifier', $excludeFolder.'%'),
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
                            $this->extensionConfiguration->getMimeTypes(),
                        ),
                    ],
                    $excludeFoldersConstraints,
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
                'SUM(CASE WHEN compressed = 1 THEN 1 ELSE 0 END) AS compressed',
                'SUM(CASE WHEN compressed = 0 AND (compress_error IS NULL OR compress_error = \'\') THEN 1 ELSE 0 END) AS not_compressed',
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
     *
     * Rows whose compressed output ended up larger than the original (the
     * provider still marks these as "compressed") are excluded from the
     * sum instead of contributing a negative delta, so a single non-saving
     * compression cannot make the total negative or understate the savings
     * from other files.
     */
    public function getTotalBytesSaved(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $result = $queryBuilder
            ->selectLiteral('SUM(CASE WHEN compress_original_size > compress_size THEN compress_original_size - compress_size ELSE 0 END) AS saved')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('compressed', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) ($result ?? 0);
    }
}
