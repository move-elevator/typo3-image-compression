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

return [
    'tx_typo3imagecompression_restore' => [
        'path' => '/typo3-image-compression/restore',
        'methods' => ['POST'],
        'target' => MoveElevator\Typo3ImageCompression\Controller\RestoreFileController::class.'::mainAction',
    ],
];
