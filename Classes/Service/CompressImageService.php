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

namespace MoveElevator\Typo3ImageCompression\Service;

use MoveElevator\Typo3ImageCompression\Configuration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use RuntimeException;
use SplFileObject;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException, ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\{Exception, SingletonInterface};
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\{IllegalObjectTypeException, UnknownObjectException};
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use function in_array;

/**
 * CompressImageService.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class CompressImageService implements SingletonInterface
{
    protected array $extConf = [];

    public function __construct(
        protected FileRepository $fileRepository,
        protected PersistenceManager $persistenceManager,
    ) {}

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function initAction(): void
    {
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(Configuration::EXT_KEY);

        if( '' === $this->getApiKey()) {
            return;
        }

        \Tinify\setKey($this->getApiKey());
        \Tinify\validate();
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws Exception
     */
    public function initializeCompression(File $file): void
    {
        $this->initAction();

        if ($this->isFileInExcludeFolder($file)) {
            return;
        }

        if (
            !in_array(
                strtolower($file->getMimeType()),
                [
                    'image/png',
                    'image/jpeg',
                ],
                true,
            )
        ) {
            return;
        }

        if (0 === (int) ($this->extConf['debug'] ?? 1)) {
            try {
                $this->assureFileExists($file);
                $originalFileSize = $file->getSize();
                $publicUrl = $this->getPublicPath().urldecode($file->getPublicUrl());
                $source = \Tinify\fromFile($publicUrl);
                $source->toFile($publicUrl);
                $fileSize = $this->setCompressedForCurrentFile($file);

                if (0 !== (int) $fileSize) {
                    $percentageSaved = (int) (100 - ((100 / $originalFileSize) * $fileSize));
                    $this->addMessageToFlashMessageQueue(
                        'success',
                        [0 => $percentageSaved.'%'],
                        ContextualFeedbackSeverity::INFO,
                    );
                }
                $this->updateFileInformation($file);
            } catch (\Exception $e) {
                $this->saveError($file, $e);
                $this->addMessageToFlashMessageQueue(
                    'compressionFailed',
                    [0 => $e->getMessage()],
                    ContextualFeedbackSeverity::WARNING,
                );
            }
        } else {
            $this->addMessageToFlashMessageQueue('debugMode', [], ContextualFeedbackSeverity::INFO);
        }
    }

    protected function getPublicPath(): string
    {
        return Environment::getPublicPath().'/';
    }

    protected function getAbsoluteFileName(File $file): string
    {
        return urldecode(
            rtrim(Environment::getPublicPath(), '/').'/'.ltrim($file->getPublicUrl(), '/'),
        );
    }

    /**
     * @throws \Exception
     */
    protected function assureFileExists(File $file): void
    {
        $absFileName = $this->getAbsoluteFileName($file);
        if (false === file_exists($absFileName)) {
            throw new RuntimeException(Configuration::EXT_NAME . ': File does not exist: '.$absFileName, 1575270381);
        }
        if (0 === (int) filesize($absFileName)) {
            throw new RuntimeException(Configuration::EXT_NAME . ': Filesize is 0: '.$absFileName, 1575270380);
        }
    }

    /**
     * @throws Exception
     */
    protected function isFileInExcludeFolder(File $file): bool
    {
        if (isset($this->extConf['excludeFolders']) && '' !== $this->extConf['excludeFolders']) {
            $excludeFolders = GeneralUtility::trimExplode(',', $this->extConf['excludeFolders'], true);
            $identifier = $file->getIdentifier();
            foreach ($excludeFolders as $excludeFolder) {
                if (str_starts_with($identifier, $excludeFolder)) {
                    $this->addMessageToFlashMessageQueue(
                        'folderExcluded',
                        [0 => $excludeFolder],
                        ContextualFeedbackSeverity::INFO,
                    );

                    return true;
                }
            }
        }

        return false;
    }

    protected function getApiKey(): string
    {
        return (string) $this->extConf['apiKey'];
    }

    protected function updateFileInformation(File $file): void
    {
        $storage = $file->getStorage();
        $fileIndexer = GeneralUtility::makeInstance(Indexer::class, $storage);
        $fileIndexer->updateIndexEntry($file);
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function setCompressedForCurrentFile(File $file): ?int
    {
        /** @var \MoveElevator\Typo3ImageCompression\Domain\Model\File $extbaseFileObject */
        $extbaseFileObject = $this->fileRepository->findByUid($file->getUid());
        $extbaseFileObject->setCompressed(true);
        $extbaseFileObject->resetCompressError();
        $this->fileRepository->update($extbaseFileObject);
        $this->persistenceManager->persistAll();
        try {
            clearstatcache();
            $splFileObject = new SplFileObject($this->getAbsoluteFileName($file));

            return (int) $splFileObject->getSize();
        } catch (\Exception) {
            return null;
        }
    }

    protected function isCli(): bool
    {
        return Environment::isCli();
    }

    /**
     * @throws Exception
     */
    protected function addMessageToFlashMessageQueue(
        string $key,
        array $replaceMarkers = [],
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR,
    ): void {
        if ($this->isCli()) {
            return;
        }

        $message = LocalizationUtility::translate(
            'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:flashMessage.message.'.$key,
            null,
            $replaceMarkers,
        );
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            LocalizationUtility::translate(
                'LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:flashMessage.title',
            ),
            $severity,
            true,
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $defaultFlashMessageQueue->enqueue($flashMessage);
    }

    /**
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     */
    protected function saveError(File $file, \Exception $e): void
    {
        /** @var \MoveElevator\Typo3ImageCompression\Domain\Model\File $extbaseFileObject */
        $extbaseFileObject = $this->fileRepository->findByUid($file->getUid());
        $extbaseFileObject->setCompressed(false);
        $extbaseFileObject->setCompressError($e->getCode().' : '.$e->getMessage());
        $this->fileRepository->update($extbaseFileObject);
        $this->persistenceManager->persistAll();
    }
}
