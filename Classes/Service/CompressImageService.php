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
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use RuntimeException;
use SplFileObject;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException,
    ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\{Exception, Resource\ResourceStorage, Resource\StorageRepository, SingletonInterface};
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageService};
use TYPO3\CMS\Core\Resource\{File, FileInterface};
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
    public function __construct(
        protected FileRepository $fileRepository,
        protected FileProcessedRepository $fileProcessedRepository,
        protected PersistenceManager $persistenceManager,
        protected ExtensionConfiguration $extensionConfiguration,
        protected StorageRepository $storageRepository,
    ) {}

    /**
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function initAction(): void
    {
        if ('' === $this->extensionConfiguration->getApiKey()) {
            return;
        }

        \Tinify\setKey($this->extensionConfiguration->getApiKey());
        \Tinify\validate();
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws Exception
     */
    public function compress(File|FileInterface $file): void
    {
        if (!$file instanceof File) {
            return;
        }

        $this->initAction();

        if ($this->isFileInExcludeFolder($file)) {
            return;
        }

        if (
            !in_array(
                strtolower($file->getMimeType()),
                $this->extensionConfiguration->getMimeTypes(),
                true,
            )
        ) {
            return;
        }

        if (!$this->extensionConfiguration->isDebug()) {
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

    /**
     * @param mixed[] $files
     */
    public function compressProcessedFiles(array $files): void
    {
        $this->initAction();

        foreach ($files as $file) {
            $fileId = $file['uid'];
            $fileStorageId = $this->fileProcessedRepository->findStorageId($fileId);

            if (0 === $fileStorageId) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'file storage not found');
                continue;
            }

            /** @var ResourceStorage $storage */
            $storage = $this->storageRepository->getStorageObject($fileStorageId);
            $filePath = $this->getPublicPath().($storage->getConfiguration()['basePath'] ?? '').urldecode($file['identifier']);

            if (false === file_exists($filePath)) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'file not found');
                continue;
            }

            if (0 === (int) filesize($filePath)) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'filesize invalid');
                continue;
            }

            if (false === in_array(mime_content_type($filePath), $this->extensionConfiguration->getMimeTypes(), true)) {
                continue;
            }

            try {
                $source = \Tinify\fromFile($filePath);

                if (false !== $source->toFile($filePath)) {
                    $this->fileProcessedRepository->updateCompressState($fileId);
                }
            } catch (\Exception $e) {
                $this->addMessageToFlashMessageQueue(
                    'compressionFailed',
                    [0 => $e->getMessage()],
                    ContextualFeedbackSeverity::WARNING,
                );
            }
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
            throw new RuntimeException(Configuration::EXT_NAME.': File does not exist: '.$absFileName, 1575270381);
        }
        if (0 === (int) filesize($absFileName)) {
            throw new RuntimeException(Configuration::EXT_NAME.': Filesize is 0: '.$absFileName, 1575270380);
        }
    }

    /**
     * @throws Exception
     */
    protected function isFileInExcludeFolder(File $file): bool
    {
        $excludeFolders = $this->extensionConfiguration->getExcludeFolders();
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

        return false;
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
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:flashMessage.message.'.$key,
            null,
            $replaceMarkers,
        );
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            LocalizationUtility::translate(
                'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:flashMessage.title',
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
