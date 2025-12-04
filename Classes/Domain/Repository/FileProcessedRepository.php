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

namespace MoveElevator\Typo3ImageCompression\Domain\Repository;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FileProcessedRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class FileProcessedRepository
{
    public function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->getTableName());
    }

    /**
     * @param string[] $columns
     *
     * @return mixed[]
     */
    public function findAllNonCompressed(array $columns = ['*'], int $limit = 0): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select(...$columns)
            ->from($this->getTableName())
            ->where(
                $queryBuilder->expr()->eq('compressed', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->isNull('compress_error'),
                $queryBuilder->expr()->isNotNull('name'),
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
            ->set('compressed', $state);

        if (false === empty($errorMessage)) {
            $queryBuilder->set('compress_error', $errorMessage);
        }

        $queryBuilder->executeStatement();
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

        return $result ? (int) $result['storage'] : 0;
    }

    protected function getTableName(): string
    {
        return 'sys_file_processedfile';
    }
}
