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
    'compress_provider' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:compress_provider',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
            'default' => '',
        ],
    ],
    'compress_tool' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:compress_tool',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
            'default' => '',
        ],
    ],
    'compress_original_size' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:compress_original_size',
        'config' => [
            'type' => 'number',
            'readOnly' => true,
            'default' => 0,
        ],
    ],
    'compress_size' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:compress_size',
        'config' => [
            'type' => 'number',
            'readOnly' => true,
            'default' => 0,
        ],
    ],
    'compress_tstamp' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:compress_tstamp',
        'config' => [
            'type' => 'datetime',
            'readOnly' => true,
            'default' => 0,
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file',
    'compress_error,compress_provider,compress_tool,compress_original_size,compress_size,compress_tstamp',
);
