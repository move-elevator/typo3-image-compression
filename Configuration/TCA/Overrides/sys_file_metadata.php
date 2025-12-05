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

/*
 * Display compression info from sys_file in the metadata form.
 * The actual data is stored in sys_file, but shown here for convenience.
 */
ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', [
    'tx_imagecompression_info' => [
        'exclude' => true,
        'label' => 'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:field.compression_info',
        'config' => [
            'type' => 'user',
            'renderType' => 'imageCompressionInfo',
        ],
    ],
]);

// Insert compression info after fileinfo field in the General tab
foreach ($GLOBALS['TCA']['sys_file_metadata']['types'] ?? [] as $type => $config) {
    if (isset($config['showitem'])) {
        $GLOBALS['TCA']['sys_file_metadata']['types'][$type]['showitem'] = str_replace(
            'fileinfo,',
            'fileinfo, tx_imagecompression_info,',
            $config['showitem'],
        );
    }
}
