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

use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;

/**
 * FileResolver.
 *
 * Thin seam around TYPO3 core's ResourceFactory::getFileObject(). That class
 * is declared `readonly` from TYPO3 13.4 onwards but not in 12.4, which this
 * extension supports side by side; depending on this interface instead of
 * the concrete class keeps consumers mockable regardless of which TYPO3
 * major is installed.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface FileResolver
{
    /**
     * @throws FileDoesNotExistException
     */
    public function getFileObject(int $uid): File;
}
