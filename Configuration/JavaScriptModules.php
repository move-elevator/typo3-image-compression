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
    'dependencies' => [
        'backend',
        'filelist',
    ],
    // Without this tag, RestoreFileContextMenuAction.js is unreachable: the
    // native context menu is a top-level UI component, with its own import
    // map separate from whichever module rendered the iframe underneath it.
    // TYPO3\CMS\Backend\ContextMenu\ImportMapConfigurator only pulls a
    // package's imports into that top-level map when @typo3/backend/context-menu.js
    // itself loads, and only for packages tagged 'backend.contextmenu' here
    // (see EXT:filelist's own JavaScriptModules.php for the same pattern).
    'tags' => [
        'backend.contextmenu',
    ],
    'imports' => [
        '@move-elevator/typo3-image-compression/' => 'EXT:typo3_image_compression/Resources/Public/JavaScript/',
    ],
];
