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

use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreButton.
 *
 * A file-list action button that restores a file's backup. Rendered as a
 * plain POST form (not a link) so the mutation cannot be triggered by
 * simple navigation, matching how EXT:filelist's own "download" action works.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class RestoreButton implements ButtonInterface
{
    public function __construct(
        private readonly string $actionUrl,
        private readonly int $fileUid,
        private readonly string $label,
        private readonly Icon $icon,
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
        $formAttributes = GeneralUtility::implodeAttributes([
            'method' => 'post',
            'action' => $this->actionUrl,
            'class' => 'd-inline',
        ], true);

        $buttonAttributes = GeneralUtility::implodeAttributes([
            'type' => 'submit',
            'class' => 'btn btn-sm btn-default',
            'title' => $this->label,
        ], true);

        return '<form '.$formAttributes.'>'
            .'<input type="hidden" name="fileUid" value="'.$this->fileUid.'">'
            .'<button '.$buttonAttributes.'>'
            .$this->icon->render()
            .'</button>'
            .'</form>';
    }
}
