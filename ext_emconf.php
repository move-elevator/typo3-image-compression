<?php

/*
 * This file is part of the "typo3_image_compression" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'TYPO3 Image Compression',
    'description' => '',
    'category' => 'be',
    'author' => 'Konrad Michalik',
    'author_email' => 'km@move-elevator.de',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '12.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
