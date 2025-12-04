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

namespace MoveElevator\Typo3ImageCompression\Compression;

use Exception;
use MoveElevator\Typo3ImageCompression\Configuration;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use RuntimeException;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException,
    ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Persistence\Exception\{IllegalObjectTypeException, UnknownObjectException};
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

use function in_array;

/**
 * TinifyCompressor.
 *
 * @see https://tinypng.com/developers
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class TinifyCompressor implements CompressorInterface, QuotaAwareInterface, SingletonInterface
{
    use CompressorTrait;
    use FlashMessageTrait;

    private const PROVIDER_IDENTIFIER = 'tinify';
    private const FREE_TIER_LIMIT = 500;

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly FileProcessedRepository $fileProcessedRepository,
        protected readonly PersistenceManager $persistenceManager,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly StorageRepository $storageRepository,
    ) {}

    public function getProviderIdentifier(): string
    {
        return self::PROVIDER_IDENTIFIER;
    }

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
     * Returns the current compression count from the TinyPNG API.
     * Returns null if the API key is not configured or validation fails.
     */
    public function getCompressionCount(): ?int
    {
        try {
            $this->initAction();

            return \Tinify\getCompressionCount();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns the quota limit for TinyPNG.
     * Free tier has 500 compressions/month, paid plans are unlimited.
     */
    public function getQuotaLimit(): ?int
    {
        $compressionCount = $this->getCompressionCount();

        if (null === $compressionCount) {
            return null;
        }

        // If count exceeds free tier limit, assume paid plan (unlimited)
        if ($compressionCount > self::FREE_TIER_LIMIT) {
            return null;
        }

        return self::FREE_TIER_LIMIT;
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
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
                $filePath = $this->getAbsoluteFilePath($file);
                $source = \Tinify\fromFile($filePath);
                $source->toFile($filePath);

                $this->markFileAsCompressed($file);
                $this->updateFileInformation($file);

                clearstatcache(true, $filePath);
                $newFileSize = (int) filesize($filePath);
                $percentageSaved = $this->calculateSavedPercent($originalFileSize, $newFileSize);

                if ($percentageSaved > 0) {
                    $this->addFlashMessage(
                        'success',
                        [$percentageSaved.'%'],
                        ContextualFeedbackSeverity::INFO,
                    );
                }
            } catch (Exception $e) {
                $this->saveError($file, $e);
                $this->addFlashMessage(
                    'compressionFailed',
                    [$e->getMessage()],
                    ContextualFeedbackSeverity::WARNING,
                );
            }
        } else {
            $this->addFlashMessage('debugMode', [], ContextualFeedbackSeverity::INFO);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $files
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
            $storage = $this->storageRepository->getStorageObject(max(0, $fileStorageId));
            $filePath = Environment::getPublicPath().'/'.($storage->getConfiguration()['basePath'] ?? '').urldecode($file['identifier']);

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
            } catch (Exception $e) {
                $this->addFlashMessage(
                    'compressionFailed',
                    [$e->getMessage()],
                    ContextualFeedbackSeverity::WARNING,
                );
            }
        }
    }

    /**
     * Override trait method to add flash message when folder is excluded.
     */
    protected function isFileInExcludeFolder(File $file): bool
    {
        $excludeFolders = $this->extensionConfiguration->getExcludeFolders();
        $identifier = $file->getIdentifier();

        foreach ($excludeFolders as $excludeFolder) {
            if (str_starts_with($identifier, $excludeFolder)) {
                $this->addFlashMessage(
                    'folderExcluded',
                    [$excludeFolder],
                    ContextualFeedbackSeverity::INFO,
                );

                return true;
            }
        }

        return false;
    }

    /**
     * @throws Exception
     */
    protected function assureFileExists(File $file): void
    {
        $absFileName = $this->getAbsoluteFilePath($file);
        if (false === file_exists($absFileName)) {
            throw new RuntimeException(Configuration::EXT_NAME.': File does not exist: '.$absFileName, 1575270381);
        }
        if (0 === (int) filesize($absFileName)) {
            throw new RuntimeException(Configuration::EXT_NAME.': Filesize is 0: '.$absFileName, 1575270380);
        }
    }

    /**
     * @throws UnknownObjectException
     * @throws IllegalObjectTypeException
     */
    protected function saveError(File $file, Exception $e): void
    {
        /** @var \MoveElevator\Typo3ImageCompression\Domain\Model\File $extbaseFileObject */
        $extbaseFileObject = $this->fileRepository->findByUid($file->getUid());
        $extbaseFileObject->setCompressed(false);
        $extbaseFileObject->setCompressError($e->getCode().' : '.$e->getMessage());
        $this->fileRepository->update($extbaseFileObject);
        $this->persistenceManager->persistAll();
    }
}
