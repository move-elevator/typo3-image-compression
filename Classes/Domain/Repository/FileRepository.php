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

use MoveElevator\Typo3ImageCompression\Domain\Model\FileStorage;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\{QueryInterface, QueryResultInterface, Repository};

/**
 * FileRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 *
 * @extends Repository<\MoveElevator\Typo3ImageCompression\Domain\Model\File>
 */
class FileRepository extends Repository
{
    public function createQuery(): QueryInterface
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query;
    }

    /**
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
                            [
                                'image/png',
                                'image/jpeg',
                            ],
                        ),
                    ],
                    $excludeFoldersConstraints,
                ),
            ),
        );
        $query->setLimit($limit);

        return $query->execute();
    }
}
