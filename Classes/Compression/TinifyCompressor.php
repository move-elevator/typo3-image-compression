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

namespace MoveElevator\Typo3ImageCompression\Compression;

use Exception;
use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use MoveElevator\Typo3ImageCompression\Compression\Exception\CompressionAbortedException;
use MoveElevator\Typo3ImageCompression\Configuration;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use RuntimeException;
use Tinify\{AccountException, ConnectionException, ServerException};
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException,
    ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

use function in_array;
use function strlen;

/**
 * TinifyCompressor.
 *
 * @see https://tinypng.com/developers
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class TinifyCompressor implements CompressorInterface, QuotaAwareInterface, LoggerAwareInterface, SingletonInterface
{
    use CompressorTrait;
    use FlashMessageTrait;
    use LoggerAwareTrait;

    private const PROVIDER_IDENTIFIER = 'tinify';
    private const FREE_TIER_LIMIT = 500;
    private const CACHE_ENTRY_COMPRESSION_COUNT = 'compression-count';
    private const CACHE_LIFETIME = 900;

    private ?int $compressionCount = null;
    private bool $compressionCountResolved = false;

    private bool $initialized = false;

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly FileProcessedRepository $fileProcessedRepository,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly StorageRepository $storageRepository,
        protected readonly FrontendInterface $cache,
        protected readonly BackupService $backupService,
    ) {}

    public function getProviderIdentifier(): string
    {
        return self::PROVIDER_IDENTIFIER;
    }

    /**
     * Configures and validates the TinyPNG client.
     *
     * Runs at most once per request: the API key is validated a single time
     * regardless of how many files are compressed in a batch.
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function initAction(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $apiKey = $this->extensionConfiguration->getApiKey();

        if ('' === $apiKey) {
            return;
        }

        \Tinify\setKey($apiKey);
        \Tinify\validate();
    }

    /**
     * Returns the current compression count from the TinyPNG API.
     * Returns null if the API key is not configured or validation fails.
     *
     * The count is cached (both per request and persistently) to avoid an
     * API round-trip on every backend request.
     */
    public function getCompressionCount(): ?int
    {
        if ($this->compressionCountResolved) {
            return $this->compressionCount;
        }

        $cached = $this->cache->get(self::CACHE_ENTRY_COMPRESSION_COUNT);

        if (false !== $cached) {
            $this->compressionCount = null === $cached ? null : (int) $cached;
            $this->compressionCountResolved = true;

            return $this->compressionCount;
        }

        $count = $this->fetchCompressionCount();

        $this->cache->set(self::CACHE_ENTRY_COMPRESSION_COUNT, $count, [], self::CACHE_LIFETIME);
        $this->compressionCount = $count;
        $this->compressionCountResolved = true;

        return $count;
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

    public function compress(File|FileInterface $file): CompressionOutcome
    {
        if (!$file instanceof File) {
            return CompressionOutcome::Skipped;
        }

        if ($this->isFileInExcludeFolder($file)) {
            return CompressionOutcome::Skipped;
        }

        if (
            !in_array(
                strtolower($file->getMimeType()),
                $this->extensionConfiguration->getMimeTypes(),
                true,
            )
        ) {
            return CompressionOutcome::Skipped;
        }

        if ($this->extensionConfiguration->isDebug()) {
            $this->addFlashMessage('debugMode', [], ContextualFeedbackSeverity::INFO);

            return CompressionOutcome::Skipped;
        }

        if (!$this->isLocalStorage($file->getStorage())) {
            $this->rejectUnsupportedStorage($file);

            return CompressionOutcome::Failed;
        }

        try {
            $this->initAction();
            $this->assureFileExists($file);
            $originalFileSize = (int) $file->getSize();
            $filePath = $this->getAbsoluteFilePath($file);
            $this->maybeBackupOriginal($file, $filePath);
            /** @var \Tinify\Source $source */
            $source = \Tinify\fromFile($filePath);
            $source = $this->applyPreserveOptions($source);
            /** @var \Tinify\Result $result */
            $result = $source->result();
            // strlen(toBuffer()) rather than Result::size() (which reads the
            // "content-length" response header): it reflects the exact bytes
            // that would be written and does not depend on that header being
            // present.
            $newFileSize = strlen($result->toBuffer());

            if (!$this->meetsMinimumSaving($originalFileSize, $newFileSize)) {
                $compressInfo = $this->buildSkippedInfo(self::PROVIDER_IDENTIFIER, $originalFileSize);
                $this->markFileAsOptimal($file, $compressInfo);
                $this->addFlashMessage('alreadyOptimal', [], ContextualFeedbackSeverity::INFO);

                return CompressionOutcome::Skipped;
            }

            $result->toFile($filePath);
            $percentageSaved = $this->calculateSavedPercent($originalFileSize, $newFileSize);

            $compressInfo = $this->buildCompressInfo(self::PROVIDER_IDENTIFIER, $originalFileSize, $newFileSize);
            $this->markFileAsCompressed($file, $compressInfo);
            $this->updateFileInformation($file);

            if ($percentageSaved > 0) {
                $this->addFlashMessage(
                    'success',
                    [$percentageSaved.'%'],
                    ContextualFeedbackSeverity::INFO,
                );
            }

            return CompressionOutcome::Compressed;
        } catch (AccountException $e) {
            $this->logger?->critical('TinyPNG account error, aborting compression run', [
                'file' => $file->getIdentifier(),
                'message' => $e->getMessage(),
            ]);
            $this->addFlashMessage(
                'compressionFailed',
                [$e->getMessage()],
                ContextualFeedbackSeverity::WARNING,
            );

            throw new CompressionAbortedException($e->getMessage(), 0, $e);
        } catch (ServerException|ConnectionException $e) {
            // Transient failure: leave the file uncompressed without an error
            // record, so the next scheduled run picks it up again.
            $this->logger?->warning('Transient TinyPNG error, file will be retried on next run', [
                'file' => $file->getIdentifier(),
                'message' => $e->getMessage(),
            ]);
            $this->addFlashMessage(
                'compressionFailed',
                [$e->getMessage()],
                ContextualFeedbackSeverity::WARNING,
            );

            return CompressionOutcome::Failed;
        } catch (Exception $e) {
            $this->saveError($file, $e);
            $this->addFlashMessage(
                'compressionFailed',
                [$e->getMessage()],
                ContextualFeedbackSeverity::WARNING,
            );

            return CompressionOutcome::Failed;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    public function compressProcessedFiles(array $files): void
    {
        // Deferred until a local file is actually present: initAction()
        // validates the TinyPNG API key with a real HTTP request, which a
        // batch made up only of remote-storage files should never trigger.
        if ($this->hasLocalStorageFile($files)) {
            try {
                $this->initAction();
            } catch (AccountException $e) {
                $this->logger?->critical('TinyPNG account error, aborting compression run', [
                    'message' => $e->getMessage(),
                ]);

                throw new CompressionAbortedException($e->getMessage(), 0, $e);
            } catch (ServerException|ConnectionException $e) {
                // Transient failure during initialization: leave the whole
                // batch unprocessed without an error record, so it is retried
                // on the next scheduled run, consistent with the per-file
                // transient handling in compressSingleProcessedFile().
                $this->logger?->warning('Transient TinyPNG error during initialization, batch will be retried on next run', [
                    'message' => $e->getMessage(),
                ]);

                return;
            }
        }

        foreach ($files as $file) {
            $this->compressSingleProcessedFile($file);
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

    protected function saveError(File $file, Exception $e): void
    {
        $errorMessage = $e->getCode().' : '.$e->getMessage();
        $this->fileRepository->updateCompressionStatus($file->getUid(), false, $errorMessage, '');
    }

    /**
     * Applies configured metadata preservation. GPS location is never
     * preserved, it is a data protection concern rather than a compression setting.
     *
     * The TinyPNG API's `preserve()` option only supports "copyright" and
     * "creation"; there is no ICC-profile-preservation option, TinyPNG
     * always converts images to sRGB. `preserveColorProfile` therefore has
     * no effect for this provider (see README.md's provider support table).
     */
    protected function applyPreserveOptions(\Tinify\Source $source): \Tinify\Source
    {
        $options = [];

        if ($this->extensionConfiguration->isPreserveCopyright()) {
            $options[] = 'copyright';
        }

        if ($this->extensionConfiguration->isPreserveCreationDate()) {
            $options[] = 'creation';
        }

        if ([] === $options) {
            return $source;
        }

        return $source->preserve(...$options);
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function hasLocalStorageFile(array $files): bool
    {
        foreach ($files as $file) {
            $fileStorageId = $this->fileProcessedRepository->findStorageId((int) ($file['uid'] ?? 0));

            if (0 === $fileStorageId) {
                continue;
            }

            /** @var ResourceStorage $storage */
            $storage = $this->storageRepository->getStorageObject(max(0, $fileStorageId));

            if ($this->isLocalStorage($storage)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function compressSingleProcessedFile(array $file): void
    {
        $fileId = $file['uid'];
        $fileStorageId = $this->fileProcessedRepository->findStorageId($fileId);

        if (0 === $fileStorageId) {
            $this->fileProcessedRepository->updateCompressState($fileId, 0, 'file storage not found');

            return;
        }

        /** @var ResourceStorage $storage */
        $storage = $this->storageRepository->getStorageObject(max(0, $fileStorageId));

        if (!$this->isLocalStorage($storage)) {
            $this->fileProcessedRepository->updateCompressState($fileId, 0, 'unsupported storage driver: '.$storage->getDriverType());

            return;
        }

        $filePath = $this->resolveProcessedFilePath($storage, (string) $file['identifier']);

        if (null === $filePath || false === file_exists($filePath)) {
            $this->fileProcessedRepository->updateCompressState($fileId, 0, 'file not found');

            return;
        }

        if (0 === (int) filesize($filePath)) {
            $this->fileProcessedRepository->updateCompressState($fileId, 0, 'filesize invalid');

            return;
        }

        if (false === in_array(mime_content_type($filePath), $this->extensionConfiguration->getMimeTypes(), true)) {
            return;
        }

        try {
            /** @var \Tinify\Source $source */
            $source = \Tinify\fromFile($filePath);
            $source = $this->applyPreserveOptions($source);

            if (false !== $source->toFile($filePath)) {
                $this->fileProcessedRepository->updateCompressState($fileId);
            } else {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'failed to write compressed file');
            }
        } catch (AccountException $e) {
            $this->logger?->critical('TinyPNG account error, aborting compression run', [
                'file' => $file['identifier'] ?? $fileId,
                'message' => $e->getMessage(),
            ]);

            throw new CompressionAbortedException($e->getMessage(), 0, $e);
        } catch (ServerException|ConnectionException $e) {
            // Transient failure: leave the file uncompressed without an
            // error record, so the next scheduled run picks it up again.
            $this->logger?->warning('Transient TinyPNG error, file will be retried on next run', [
                'file' => $file['identifier'] ?? $fileId,
                'message' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            // Persist the error so the file is not retried on every run,
            // which would otherwise keep consuming the TinyPNG quota.
            $this->fileProcessedRepository->updateCompressState($fileId, 0, $e->getCode().' : '.$e->getMessage());
            $this->addFlashMessage(
                'compressionFailed',
                [$e->getMessage()],
                ContextualFeedbackSeverity::WARNING,
            );
        }
    }

    private function fetchCompressionCount(): ?int
    {
        try {
            $this->initAction();

            return \Tinify\getCompressionCount();
        } catch (Exception) {
            return null;
        }
    }
}
