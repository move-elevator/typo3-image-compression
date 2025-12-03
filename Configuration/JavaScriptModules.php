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

return [
    'dependencies' => [
        'backend',
        'filelist',
    ],
    'imports' => [
        '@move-elevator/typo3-image-compression/' => 'EXT:typo3_image_compression/Resources/Public/JavaScript/',
    ],
];
