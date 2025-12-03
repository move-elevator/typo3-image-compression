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
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * FileProcessedRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class FileProcessedRepository extends Repository
{
    public function __construct(
        private readonly \TYPO3\CMS\Core\Database\ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    /**
     * @param string[] $columns
     */
    public function findAllNonCompressed(array $columns = ['*'], int $limit = 0): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $query->matching(
            $query->logicalAnd(
                $query->equals('compressed', 0),
                $query->logicalNot($query->equals('name', null)),
            ),
        );

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        // If you need raw data instead of domain objects, use:
        return $query->execute(true);

        // For domain objects, use:
        // return $query->execute()->toArray();
    }

    public function updateCompressState(int $processedFileId): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_processedfile');
        $queryBuilder
            ->update('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($processedFileId, ParameterType::INTEGER)),
            )
            ->set('compressed', 1)
            ->executeStatement();
    }

    public function findStorageId(int $processedFileId): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_processedfile');
        $result = $queryBuilder
            ->select('f.storage')
            ->from('sys_file_processedfile', 'pf')
            ->join(
                'pf',
                'sys_file',
                'f',
                $queryBuilder->expr()->eq('pf.original', $queryBuilder->quoteIdentifier('f.uid')),
            )
            ->where(
                $queryBuilder->expr()->eq('pf.uid', $queryBuilder->createNamedParameter($processedFileId, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        return $result ? (int) $result['storage'] : 0;
    }

    protected function getConnectionPool(): \TYPO3\CMS\Core\Database\ConnectionPool
    {
        return $this->connectionPool;
    }
}
