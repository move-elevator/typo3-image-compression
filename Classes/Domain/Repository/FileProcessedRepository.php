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

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * FileProcessedRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class FileProcessedRepository
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable($this->getTableName());
    }

    /**
     * Finds all processed files with compression errors.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllWithErrors(int $limit = 0): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $this->selectWithOriginalFileMetadata($queryBuilder)
            ->where(
                $queryBuilder->expr()->isNotNull('pf.compress_error'),
                $queryBuilder->expr()->neq('pf.compress_error', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->isNotNull('pf.name'),
            );

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllNonCompressed(int $limit = 0): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $this->selectWithOriginalFileMetadata($queryBuilder)
            ->where(
                $queryBuilder->expr()->eq('pf.compressed', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->isNull('pf.compress_error'),
                $queryBuilder->expr()->isNotNull('pf.name'),
            );

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    public function updateCompressState(int $processedFileId, int $state = 1, string $errorMessage = ''): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($processedFileId, ParameterType::INTEGER)),
            )
            ->set('compressed', $state)
            ->set('compress_error', $errorMessage);

        $queryBuilder->executeStatement();
    }

    /**
     * Classifies the outcome of a compressProcessedFiles() call for one file,
     * by reading back its row: that method reports failures/skips (missing
     * file, unavailable tool, invalid size, ...) via this same state instead
     * of throwing or returning a per-file result.
     *
     * @return 'success'|'skipped'|'errors'
     */
    public function classifyOutcome(int $processedFileId): string
    {
        $queryBuilder = $this->getQueryBuilder();
        $row = $queryBuilder
            ->select('compressed', 'compress_error')
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($processedFileId, ParameterType::INTEGER)),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (false === $row) {
            return 'errors';
        }

        if ((bool) $row['compressed']) {
            return 'success';
        }

        $hasError = null !== $row['compress_error'] && '' !== $row['compress_error'];

        return $hasError ? 'errors' : 'skipped';
    }

    public function findStorageId(int $processedFileId): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder
            ->select('f.storage')
            ->from($this->getTableName(), 'pf')
            ->join(
                'pf',
                'sys_file',
                'f',
                $queryBuilder->expr()->eq('pf.original', 'f.uid'),
            )
            ->where(
                $queryBuilder->expr()->eq('pf.uid', $queryBuilder->createNamedParameter($processedFileId, ParameterType::INTEGER)),
                $queryBuilder->expr()->isNull('pf.compress_error'),
            )
            ->executeQuery()
            ->fetchAssociative();

        return false !== $result ? (int) $result['storage'] : 0;
    }

    /**
     * Returns compression statistics for processed files.
     *
     * @return array{compressed: int, not_compressed: int, errors: int}
     */
    public function getCompressionStatistics(): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->selectLiteral(
                'SUM(CASE WHEN compressed = 1 THEN 1 ELSE 0 END) AS compressed',
                'SUM(CASE WHEN compressed = 0 AND (compress_error IS NULL OR compress_error = \'\') THEN 1 ELSE 0 END) AS not_compressed',
                'SUM(CASE WHEN compress_error IS NOT NULL AND compress_error != \'\' THEN 1 ELSE 0 END) AS errors',
            )
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->isNotNull('name'),
            )
            ->executeQuery()
            ->fetchAssociative();

        return [
            'compressed' => (int) ($result['compressed'] ?? 0),
            'not_compressed' => (int) ($result['not_compressed'] ?? 0),
            'errors' => (int) ($result['errors'] ?? 0),
        ];
    }

    protected function getTableName(): string
    {
        return 'sys_file_processedfile';
    }

    /**
     * sys_file_processedfile has no size/mime_type columns of its own; join
     * the original sys_file record to resolve them for preview purposes
     * (the processed derivative's actual size is typically close enough,
     * and exact only matters once compression actually runs).
     */
    private function selectWithOriginalFileMetadata(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder
            ->select('pf.*', 'f.size AS size', 'f.mime_type AS mime_type')
            ->from($this->getTableName(), 'pf')
            ->join(
                'pf',
                'sys_file',
                'f',
                $queryBuilder->expr()->eq('pf.original', 'f.uid'),
            );
    }
}
