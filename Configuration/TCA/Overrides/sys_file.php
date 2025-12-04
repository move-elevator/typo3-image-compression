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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::addTCAcolumns('sys_file', [
    'compressed' => [
        'exclude' => true,
        'label' => 'Compressed',
        'config' => [
            'type' => 'check',
            'readOnly' => true,
            'default' => 0,
        ],
    ],
    'compress_error' => [
        'exclude' => true,
        'label' => 'Compression Error',
        'config' => [
            'type' => 'text',
            'readOnly' => true,
            'default' => '',
        ],
    ],
    'compress_info' => [
        'exclude' => true,
        'label' => 'Compression Info',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
            'default' => '',
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes('sys_file', 'compress_error,compress_info');
