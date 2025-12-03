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

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Model\{File, FileStorage};
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository, FileStorageRepository};
use MoveElevator\Typo3ImageCompression\Service\CompressImageService;
use Psr\Log\{LogLevel, LoggerInterface};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Configuration\Exception\{ExtensionConfigurationExtensionNotConfiguredException, ExtensionConfigurationPathDoesNotExistException};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Processing\FileDeletionAspect;
use TYPO3\CMS\Core\Resource\{ResourceFactory, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\{IllegalObjectTypeException, InvalidQueryException, UnknownObjectException};
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

use function count;
use function in_array;

/**
 * CompressImageCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
// TODO: Uncomment when TYPO3 v12 support is dropped
// #[AsCommand(
//     name: 'site:compressImages',
//     description: 'Compress uncompressed images',
// )]
final class CompressImageCommand extends Command
{
    private const DEFAULT_LIMIT_TO_PROCESS = 100;

    public function __construct(
        private readonly FileStorageRepository $fileStorageRepository,
        private readonly FileRepository $fileRepository,
        private readonly FileProcessedRepository $fileProcessedRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly CompressImageService $compressImageService,
        private readonly StorageRepository $storageRepository,
        private readonly LoggerInterface $logger,
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
            'Limit of files to compress (500 images can be compressed in a month on free plan)',
            self::DEFAULT_LIMIT_TO_PROCESS,
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

        $filesProcessed = $this->fileProcessedRepository->findAllNonCompressed(limit: $limit);
        if ([] !== $filesProcessed) {
            $limit -= count($filesProcessed);

            $this->compressProcessedImages($filesProcessed);
            $this->clearPageCache();
        }

        if (0 !== $limit) {
            /** @var FileStorage $fileStorage */
            foreach ($this->fileStorageRepository->findAll() as $fileStorage) {
                $excludeFolders = $this->extensionConfiguration->getExcludeFolders();
                $files = $this->fileRepository->findAllNonCompressedInStorageWithLimit(
                    $fileStorage,
                    $limit,
                    $excludeFolders,
                );

                if ($files->count() > 0) {
                    $this->compressImages($files);
                    $this->clearPageCache();
                }
            }
        }

        return 0;
    }

    /**
     * @param QueryResultInterface<int, File> $files
     *
     * @throws FileDoesNotExistException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws Exception
     */
    private function compressImages(QueryResultInterface $files): void
    {
        $fileDeletionAspect = GeneralUtility::makeInstance(FileDeletionAspect::class);

        foreach ($files as $file) {
            $file = $this->resourceFactory->getFileObject($file->getUid());
            $this->compressImageService->initializeCompression($file);
            $fileDeletionAspect->cleanupProcessedFilesPostFileReplace(
                new AfterFileReplacedEvent($file, ''),
            );
        }
    }

    /**
     * @param mixed[] $files
     */
    private function compressProcessedImages(array $files): void
    {
        $publicUrl = Environment::getPublicPath().'/';
        \Tinify\setKey($this->extensionConfiguration->getApiKey());

        foreach ($files as $file) {
            $fileId = $file['uid'];
            $fileStorageId = $this->fileProcessedRepository->findStorageId($fileId);

            if (0 === $fileStorageId) {
                continue;
            }

            /** @var ResourceStorage $storage */
            $storage = $this->storageRepository->getStorageObject($fileStorageId);
            $filePath = $publicUrl.($storage->getConfiguration()['basePath'] ?? '').urldecode((string) $file['identifier']);

            if (false === file_exists($filePath)) {
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
                $this->logger->log(LogLevel::ERROR, $e->getMessage(), ['filePath' => $filePath]);
            }
        }
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
