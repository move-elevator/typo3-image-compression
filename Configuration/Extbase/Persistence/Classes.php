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

use MoveElevator\Typo3ImageCompression\Domain\Model\{File, FileStorage};

return [
    FileStorage::class => [
        'tableName' => 'sys_file_storage',
    ],
    File::class => [
        'tableName' => 'sys_file',
    ],
];
