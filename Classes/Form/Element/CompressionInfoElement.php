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

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use MoveElevator\Typo3ImageCompression\Utility\ViewUtility;
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
     *
     * @throws Exception
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
            $result['html'] = $this->renderTemplate('no_file');

            return $result;
        }

        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        $fileData = $fileRepository->findCompressionStatusByUid($fileUid);

        if (null === $fileData) {
            $result['html'] = $this->renderTemplate('file_not_found');

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
        $error = $fileData['compress_error'];

        if ('' !== $error) {
            return $this->renderTemplate('error', $error);
        }

        if ($fileData['compressed']) {
            return $this->renderTemplate('compressed', $fileData['compress_info']);
        }

        return $this->renderTemplate('not_compressed');
    }

    private function renderTemplate(string $status, string $message = ''): string
    {
        return ViewUtility::renderTemplate('CompressionInfo', 'Form/', [
            'status' => $status,
            'statusLabel' => $this->translate('status.'.$status),
            'label' => $this->translate('label'),
            'message' => $message,
        ]);
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:'.$key,
        ) ?: $key;
    }
}
