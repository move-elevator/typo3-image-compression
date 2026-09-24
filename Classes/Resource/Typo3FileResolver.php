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

namespace MoveElevator\Typo3ImageCompression\Resource;

use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\{File, ResourceFactory};

/**
 * Typo3FileResolver.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class Typo3FileResolver implements FileResolver
{
    public function __construct(private ResourceFactory $resourceFactory) {}

    public function getFileObject(int $uid): File
    {
        return $this->resourceFactory->getFileObject($uid);
    }

    public function findFileByCombinedIdentifier(string $identifier): ?File
    {
        try {
            $resource = $this->resourceFactory->retrieveFileOrFolderObject($identifier);
        } catch (ResourceDoesNotExistException) {
            return null;
        }

        return $resource instanceof File ? $resource : null;
    }
}
