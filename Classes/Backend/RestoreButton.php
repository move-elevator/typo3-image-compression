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

namespace MoveElevator\Typo3ImageCompression\Backend;

use Stringable;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreButton.
 *
 * A file-list action button that restores a file's backup. Rendered as a
 * plain <button>, not a <form>: this button is placed inside EXT:filelist's
 * own page-level <form name="fileListForm">, and a nested <form> is invalid
 * HTML. Browsers silently drop the inner <form> tag and reassociate its
 * inputs with the outer form, so the submission would never reach
 * RestoreFileController. The RestoreFileAction.js module reads this button's
 * data attributes and submits a detached, out-of-band form instead.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class RestoreButton implements ButtonInterface, Stringable
{
    public function __construct(
        private string $actionUrl,
        private int $fileUid,
        private string $label,
        private Icon $icon,
        private string $formToken,
    ) {}

    public function __toString(): string
    {
        return $this->render();
    }

    public function isValid(): bool
    {
        return '' !== trim($this->label) && $this->fileUid > 0;
    }

    public function getType(): string
    {
        return self::class;
    }

    public function render(): string
    {
        $buttonAttributes = GeneralUtility::implodeAttributes([
            'type' => 'button',
            'class' => 'btn btn-sm btn-default',
            'title' => $this->label,
            'data-restorefileaction-url' => $this->actionUrl,
            'data-restorefileaction-uid' => (string) $this->fileUid,
            'data-restorefileaction-token' => $this->formToken,
        ], true);

        return '<button '.$buttonAttributes.'>'
            .$this->icon->render()
            .'</button>';
    }
}
