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

namespace MoveElevator\Typo3ImageCompression\Command;

use MoveElevator\Typo3ImageCompression\Compression\{CompressionOutcome, CompressorInterface};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Model\{File, FileStorage};
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository, FileStorageRepository};
use MoveElevator\Typo3ImageCompression\Utility\{CompressionResultHandler, FileSizeFormatter};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException, ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Processing\FileDeletionAspect;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\{IllegalObjectTypeException, InvalidQueryException, UnknownObjectException};
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

use function count;
use function is_string;
use function sprintf;

/**
 * CompressImageCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CompressImageCommand extends Command
{
    private const DEFAULT_LIMIT_TO_PROCESS = 100;

    public function __construct(
        private readonly FileStorageRepository $fileStorageRepository,
        private readonly FileRepository $fileRepository,
        private readonly FileProcessedRepository $fileProcessedRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly CompressorInterface $compressor,
        private readonly ExtensionConfiguration $extensionConfiguration,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'limit',
            InputArgument::OPTIONAL,
            'Limit of files to compress',
            self::DEFAULT_LIMIT_TO_PROCESS,
        );
        $this->addOption(
            'include-processed',
            'p',
            InputOption::VALUE_NONE,
            'Also compress processed files (thumbnails, crops, etc.). Without this flag, only original files are compressed.',
        );
        $this->addOption(
            'retry-errors',
            'r',
            InputOption::VALUE_NONE,
            'Retry compression for files that previously failed. Clears error status on success.',
        );
        $this->addOption(
            'dry-run',
            'd',
            InputOption::VALUE_NONE,
            'List the files that would be processed, with total size and per-MIME-type counts. Writes nothing.',
        );
        $this->addOption(
            'storage',
            's',
            InputOption::VALUE_REQUIRED,
            'Limit to a single file storage by UID.',
        );
        $this->addOption(
            'folder',
            null,
            InputOption::VALUE_REQUIRED,
            'Limit to files whose identifier starts with this path (e.g. "/campaign2024/"). Applies to original files only.',
        );
    }

    /**
     * @throws InvalidQueryException
     * @throws IllegalObjectTypeException
     * @throws FileDoesNotExistException
     * @throws UnknownObjectException
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws NoSuchCacheGroupException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getArgument('limit');
        $includeProcessed = (bool) $input->getOption('include-processed');
        $retryErrors = (bool) $input->getOption('retry-errors');
        $dryRun = (bool) $input->getOption('dry-run');
        $storageUid = $this->resolveStorageUidOption($input);
        $folder = $this->resolveFolderOption($input);

        $io = new SymfonyStyle($input, $output);

        if ($dryRun) {
            $this->previewFiles($io, $limit, $includeProcessed, $retryErrors, $storageUid, $folder);

            return Command::SUCCESS;
        }

        $stats = $this->compressFiles($io, $limit, $includeProcessed, $retryErrors, $storageUid, $folder);

        // Flush the page cache only once per run, and only when files were
        // actually compressed, to avoid repeatedly invalidating the whole
        // page cache of a production site during a batch run.
        if ($stats['original']['success'] + $stats['processed']['success'] > 0) {
            $this->clearPageCache();
        }

        CompressionResultHandler::outputToConsole($output, $stats);
        CompressionResultHandler::addFlashMessage($stats);

        $errors = $stats['original']['errors'] + $stats['processed']['errors'];

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function resolveStorageUidOption(InputInterface $input): ?int
    {
        $storageOption = $input->getOption('storage');

        return null !== $storageOption ? (int) $storageOption : null;
    }

    private function resolveFolderOption(InputInterface $input): ?string
    {
        $folderOption = $input->getOption('folder');

        return is_string($folderOption) && '' !== $folderOption ? $folderOption : null;
    }

    /**
     * Compress files based on the retry flag.
     *
     * @return array{original: array{total: int, success: int, skipped: int, errors: int}, processed: array{total: int, success: int, skipped: int, errors: int}}
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function compressFiles(SymfonyStyle $io, int $limit, bool $includeProcessed, bool $retryErrors, ?int $storageUid, ?string $folder): array
    {
        $stats = [
            'original' => ['total' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
            'processed' => ['total' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        if ($includeProcessed) {
            $filesProcessed = $retryErrors
                ? $this->fileProcessedRepository->findAllWithErrors($limit)
                : $this->fileProcessedRepository->findAllNonCompressed($limit);

            if ([] !== $filesProcessed) {
                $limit -= count($filesProcessed);
                $stats['processed'] = $this->compressProcessedFilesWithStats($io, $filesProcessed);
            }
        }

        if ($limit > 0) {
            $stats['original'] = $this->compressOriginalFiles($io, $limit, $retryErrors, $storageUid, $folder);
        }

        return $stats;
    }

    /**
     * Compress original files.
     *
     * @return array{total: int, success: int, skipped: int, errors: int}
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function compressOriginalFiles(SymfonyStyle $io, int $limit, bool $retryErrors, ?int $storageUid, ?string $folder): array
    {
        $stats = ['total' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0];
        $remaining = $limit;

        $excludeFolders = $this->extensionConfiguration->getExcludeFolders();

        foreach ($this->resolveStorages($storageUid) as $fileStorage) {
            if ($remaining <= 0) {
                break;
            }

            $files = $retryErrors
                ? $this->fileRepository->findAllWithErrorsInStorageWithLimit($fileStorage, $remaining, $excludeFolders, $folder)
                : $this->fileRepository->findAllNonCompressedInStorageWithLimit($fileStorage, $remaining, $excludeFolders, $folder);

            if ($files->count() > 0) {
                $fileStats = $this->compressImagesWithStats($io, $files);
                $stats['total'] += $fileStats['total'];
                $stats['success'] += $fileStats['success'];
                $stats['skipped'] += $fileStats['skipped'];
                $stats['errors'] += $fileStats['errors'];
                $remaining -= $fileStats['total'];
            }
        }

        return $stats;
    }

    /**
     * @param QueryResultInterface<int, File> $files
     *
     * @return array{total: int, success: int, skipped: int, errors: int}
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function compressImagesWithStats(SymfonyStyle $io, QueryResultInterface $files): array
    {
        $fileDeletionAspect = GeneralUtility::makeInstance(FileDeletionAspect::class);
        $stats = ['total' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0];

        $progressBar = $io->createProgressBar($files->count());
        $progressBar->start();

        foreach ($files as $file) {
            $uid = $file->getUid();
            if (null === $uid) {
                continue;
            }

            ++$stats['total'];
            $resourceFile = $this->resourceFactory->getFileObject($uid);

            try {
                $outcome = $this->compressor->compress($resourceFile);
            } catch (Throwable) {
                $outcome = CompressionOutcome::Failed;
            }

            match ($outcome) {
                CompressionOutcome::Compressed => ++$stats['success'],
                CompressionOutcome::Skipped => ++$stats['skipped'],
                CompressionOutcome::Failed => ++$stats['errors'],
            };

            $fileDeletionAspect->cleanupProcessedFilesPostFileReplace(
                new AfterFileReplacedEvent($resourceFile, ''),
            );

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     *
     * @return array{total: int, success: int, skipped: int, errors: int}
     */
    private function compressProcessedFilesWithStats(SymfonyStyle $io, array $files): array
    {
        $stats = ['total' => count($files), 'success' => 0, 'skipped' => 0, 'errors' => 0];

        $progressBar = $io->createProgressBar(count($files));
        $progressBar->start();

        foreach ($files as $file) {
            try {
                $this->compressor->compressProcessedFiles([$file]);
                ++$stats['success'];
            } catch (Throwable) {
                ++$stats['errors'];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        return $stats;
    }

    /**
     * Lists the files that would be processed, without compressing anything.
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function previewFiles(SymfonyStyle $io, int $limit, bool $includeProcessed, bool $retryErrors, ?int $storageUid, ?string $folder): void
    {
        /** @var array<string, array{count: int, size: int}> $byMimeType */
        $byMimeType = [];
        $totalCount = 0;
        $totalSize = 0;
        $remaining = $limit;

        if ($includeProcessed) {
            $preview = $this->previewProcessedFiles($byMimeType, $remaining, $retryErrors);
            $byMimeType = $preview['byMimeType'];
            $totalCount += $preview['count'];
            $totalSize += $preview['size'];
            $remaining -= $preview['count'];
        }

        if ($remaining > 0) {
            $preview = $this->previewOriginalFiles($byMimeType, $remaining, $retryErrors, $storageUid, $folder);
            $byMimeType = $preview['byMimeType'];
            $totalCount += $preview['count'];
            $totalSize += $preview['size'];
        }

        $this->outputPreview($io, $byMimeType, $totalCount, $totalSize);
    }

    /**
     * @param array<string, array{count: int, size: int}> $byMimeType
     *
     * @return array{byMimeType: array<string, array{count: int, size: int}>, count: int, size: int}
     */
    private function previewProcessedFiles(array $byMimeType, int $limit, bool $retryErrors): array
    {
        $files = $retryErrors
            ? $this->fileProcessedRepository->findAllWithErrors($limit)
            : $this->fileProcessedRepository->findAllNonCompressed($limit);

        $size = 0;
        foreach ($files as $file) {
            $fileSize = (int) ($file['size'] ?? 0);
            $byMimeType = $this->tallyPreview($byMimeType, (string) ($file['mime_type'] ?? 'unknown'), $fileSize);
            $size += $fileSize;
        }

        return ['byMimeType' => $byMimeType, 'count' => count($files), 'size' => $size];
    }

    /**
     * @param array<string, array{count: int, size: int}> $byMimeType
     *
     * @return array{byMimeType: array<string, array{count: int, size: int}>, count: int, size: int}
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function previewOriginalFiles(array $byMimeType, int $limit, bool $retryErrors, ?int $storageUid, ?string $folder): array
    {
        $totalCount = 0;
        $totalSize = 0;
        $remaining = $limit;
        $excludeFolders = $this->extensionConfiguration->getExcludeFolders();

        foreach ($this->resolveStorages($storageUid) as $fileStorage) {
            if ($remaining <= 0) {
                break;
            }

            $files = $retryErrors
                ? $this->fileRepository->findAllWithErrorsInStorageWithLimit($fileStorage, $remaining, $excludeFolders, $folder)
                : $this->fileRepository->findAllNonCompressedInStorageWithLimit($fileStorage, $remaining, $excludeFolders, $folder);
            $storageCount = 0;

            foreach ($files as $file) {
                $uid = $file->getUid();
                if (null === $uid) {
                    continue;
                }

                $resourceFile = $this->resourceFactory->getFileObject($uid);
                $fileSize = (int) $resourceFile->getSize();
                $byMimeType = $this->tallyPreview($byMimeType, strtolower($resourceFile->getMimeType()), $fileSize);
                ++$storageCount;
                $totalSize += $fileSize;
            }

            $totalCount += $storageCount;
            $remaining -= $storageCount;
        }

        return ['byMimeType' => $byMimeType, 'count' => $totalCount, 'size' => $totalSize];
    }

    /**
     * @param array<string, array{count: int, size: int}> $byMimeType
     *
     * @return array<string, array{count: int, size: int}>
     */
    private function tallyPreview(array $byMimeType, string $mimeType, int $size): array
    {
        $entry = $byMimeType[$mimeType] ?? ['count' => 0, 'size' => 0];
        $byMimeType[$mimeType] = [
            'count' => $entry['count'] + 1,
            'size' => $entry['size'] + $size,
        ];

        return $byMimeType;
    }

    /**
     * @param array<string, array{count: int, size: int}> $byMimeType
     */
    private function outputPreview(SymfonyStyle $io, array $byMimeType, int $totalCount, int $totalSize): void
    {
        if (0 === $totalCount) {
            $io->writeln('<info>No files to compress.</info>');

            return;
        }

        $rows = [];
        foreach ($byMimeType as $mimeType => $data) {
            $rows[] = [$mimeType, $data['count'], FileSizeFormatter::format($data['size'])];
        }

        $io->newLine();
        $io->writeln('<info>Dry run: nothing was written.</info>');
        $io->table(['MIME type', 'Files', 'Total size'], $rows);
        $io->writeln(sprintf(
            '<info>%d files, %s total</info>',
            $totalCount,
            FileSizeFormatter::format($totalSize),
        ));
    }

    /**
     * @return iterable<FileStorage>
     */
    private function resolveStorages(?int $storageUid): iterable
    {
        if (null === $storageUid) {
            return $this->fileStorageRepository->findAll();
        }

        $storage = $this->fileStorageRepository->findByUid($storageUid);

        return null !== $storage ? [$storage] : [];
    }

    /**
     * @throws NoSuchCacheGroupException
     */
    private function clearPageCache(): void
    {
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $cacheManager->flushCachesInGroup('pages');
    }
}
