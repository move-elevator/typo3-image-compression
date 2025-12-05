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

namespace MoveElevator\Typo3ImageCompression\Command;

use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Model\{File, FileStorage};
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository, FileStorageRepository};
use MoveElevator\Typo3ImageCompression\Utility\CompressionResultHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
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

        $stats = [
            'original' => ['total' => 0, 'success' => 0, 'errors' => 0],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ];

        if ($includeProcessed) {
            $filesProcessed = $this->fileProcessedRepository->findAllNonCompressed(limit: $limit);

            if ([] !== $filesProcessed) {
                $limit -= count($filesProcessed);
                $stats['processed'] = $this->compressProcessedFilesWithStats($filesProcessed);
                $this->clearPageCache();
            }
        }

        if ($limit > 0) {
            /** @var FileStorage $fileStorage */
            foreach ($this->fileStorageRepository->findAll() as $fileStorage) {
                $excludeFolders = $this->extensionConfiguration->getExcludeFolders();
                $files = $this->fileRepository->findAllNonCompressedInStorageWithLimit(
                    $fileStorage,
                    $limit,
                    $excludeFolders,
                );

                if ($files->count() > 0) {
                    $fileStats = $this->compressImagesWithStats($files);
                    $stats['original']['total'] += $fileStats['total'];
                    $stats['original']['success'] += $fileStats['success'];
                    $stats['original']['errors'] += $fileStats['errors'];
                    $this->clearPageCache();
                }
            }
        }

        CompressionResultHandler::outputToConsole($output, $stats);
        CompressionResultHandler::addFlashMessage($stats);

        return 0;
    }

    /**
     * @param QueryResultInterface<int, File> $files
     *
     * @return array{total: int, success: int, errors: int}
     *
     * @throws FileDoesNotExistException
     * @throws Exception
     */
    private function compressImagesWithStats(QueryResultInterface $files): array
    {
        $fileDeletionAspect = GeneralUtility::makeInstance(FileDeletionAspect::class);
        $stats = ['total' => 0, 'success' => 0, 'errors' => 0];

        foreach ($files as $file) {
            $uid = $file->getUid();
            if (null === $uid) {
                continue;
            }

            ++$stats['total'];
            $resourceFile = $this->resourceFactory->getFileObject($uid);

            try {
                $this->compressor->compress($resourceFile);
                ++$stats['success'];
            } catch (Throwable) {
                ++$stats['errors'];
            }

            $fileDeletionAspect->cleanupProcessedFilesPostFileReplace(
                new AfterFileReplacedEvent($resourceFile, ''),
            );
        }

        return $stats;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     *
     * @return array{total: int, success: int, errors: int}
     */
    private function compressProcessedFilesWithStats(array $files): array
    {
        $stats = ['total' => count($files), 'success' => 0, 'errors' => 0];

        foreach ($files as $file) {
            try {
                $this->compressor->compressProcessedFiles([$file]);
                ++$stats['success'];
            } catch (Throwable) {
                ++$stats['errors'];
            }
        }

        return $stats;
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
