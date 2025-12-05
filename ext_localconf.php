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

use MoveElevator\Typo3ImageCompression\Form\Element\CompressionInfoElement;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1733320000] = [
    'nodeName' => 'imageCompressionInfo',
    'priority' => 50,
    'class' => CompressionInfoElement::class,
];
