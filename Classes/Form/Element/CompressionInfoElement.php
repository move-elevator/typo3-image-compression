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

namespace MoveElevator\Typo3ImageCompression\Form\Element;

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CompressionInfoElement.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class CompressionInfoElement extends AbstractFormElement
{
    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $result = $this->initializeResultArray();

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);

        if (!$extensionConfiguration->isShowCompressionStatus()) {
            return $result;
        }

        $fileUid = (int) ($this->data['databaseRow']['file'][0] ?? 0);

        if (0 === $fileUid) {
            $result['html'] = $this->wrapContent($this->translate('status.no_file'));

            return $result;
        }

        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        $fileData = $fileRepository->findCompressionStatusByUid($fileUid);

        if (null === $fileData) {
            $result['html'] = $this->wrapContent($this->translate('status.file_not_found'));

            return $result;
        }

        $result['html'] = $this->renderCompressionInfo($fileData);

        return $result;
    }

    /**
     * @param array{compressed: bool, compress_error: string, compress_info: string} $fileData
     */
    private function renderCompressionInfo(array $fileData): string
    {
        $isCompressed = $fileData['compressed'];
        $error = $fileData['compress_error'];
        $info = $fileData['compress_info'];
        $label = '<strong>'.htmlspecialchars($this->translate('label'), \ENT_QUOTES).'</strong>';

        if ('' !== $error) {
            return $this->wrapContent(
                $label
                .' <span class="badge badge-danger" style="margin-left: 8px;">'.htmlspecialchars($this->translate('status.error'), \ENT_QUOTES).'</span>'
                .'<div class="form-description" style="margin-top: 8px;">'.htmlspecialchars($error, \ENT_QUOTES).'</div>',
            );
        }

        if ($isCompressed) {
            $infoHtml = '';
            if ('' !== $info) {
                $infoHtml = '<div class="form-description" style="margin-top: 8px;">'.htmlspecialchars($info, \ENT_QUOTES).'</div>';
            }

            return $this->wrapContent(
                $label
                .' <span class="badge badge-success" style="margin-left: 8px;">'.htmlspecialchars($this->translate('status.compressed'), \ENT_QUOTES).'</span>'
                .$infoHtml,
            );
        }

        return $this->wrapContent(
            $label
            .' <span class="badge badge-warning" style="margin-left: 8px;">'.htmlspecialchars($this->translate('status.not_compressed'), \ENT_QUOTES).'</span>',
        );
    }

    private function wrapContent(string $content): string
    {
        return '<div class="formengine-field-item t3js-formengine-field-item" style="padding: 10px 0;">'
            .$content
            .'</div>';
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:'.$key,
        ) ?: $key;
    }
}
