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

use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

use function in_array;
use function sprintf;

/**
 * LocalToolsCompressor.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LocalToolsCompressor implements CompressorInterface, LoggerAwareInterface, SingletonInterface
{
    use CompressorTrait;
    use FlashMessageTrait;
    use LoggerAwareTrait;

    private const PROVIDER_IDENTIFIER = 'local-tools';

    /**
     * Tool commands with %s placeholder for file path.
     * Tools with configurable quality use getToolCommand() method instead.
     */
    private const TOOL_COMMANDS = [
        'optipng' => '-o2 -strip all %s',
        'gifsicle' => '--batch -O2 %s',
    ];

    /**
     * Maps MIME types to their preferred tools (in order of preference).
     */
    private const MIME_TYPE_TOOLS = [
        'image/jpeg' => ['jpegoptim'],
        'image/png' => ['optipng', 'pngquant'],
        'image/gif' => ['gifsicle'],
        'image/webp' => ['cwebp'],
        'image/avif' => ['avifenc'],
    ];

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly FileProcessedRepository $fileProcessedRepository,
        protected readonly PersistenceManager $persistenceManager,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly StorageRepository $storageRepository,
        protected readonly ToolDetection $toolDetection,
    ) {}

    public function getProviderIdentifier(): string
    {
        return self::PROVIDER_IDENTIFIER;
    }

    public function compress(File|FileInterface $file): void
    {
        if (!$file instanceof File) {
            return;
        }

        // Check if file is in excluded folder
        if ($this->isFileInExcludeFolder($file)) {
            return;
        }

        $mimeType = strtolower($file->getMimeType());

        // Check if MIME type is configured for compression
        if (!in_array($mimeType, $this->extensionConfiguration->getMimeTypes(), true)) {
            return;
        }

        $tool = $this->getBestToolForMimeType($mimeType);

        if (null === $tool) {
            $this->logger?->info('No suitable tool available for MIME type', [
                'mimeType' => $mimeType,
                'file' => $file->getIdentifier(),
            ]);

            return;
        }

        $filePath = $this->getAbsoluteFilePath($file);

        if (!file_exists($filePath) || 0 === (int) filesize($filePath)) {
            return;
        }

        $originalFileSize = (int) filesize($filePath);
        $success = $this->executeOptimization($tool, $filePath);

        if ($success) {
            $this->markFileAsCompressed($file);
            $this->updateFileInformation($file);

            // Log compression result and show flash message
            clearstatcache(true, $filePath);
            $newFileSize = (int) filesize($filePath);
            $savedPercent = $this->calculateSavedPercent($originalFileSize, $newFileSize);
            if ($savedPercent > 0) {
                $this->logger?->info('Image compressed', [
                    'file' => $file->getIdentifier(),
                    'tool' => $tool,
                    'originalSize' => $originalFileSize,
                    'newSize' => $newFileSize,
                    'savedPercent' => $savedPercent,
                ]);
                $this->addFlashMessage('success', [$savedPercent.'%'], ContextualFeedbackSeverity::INFO);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    public function compressProcessedFiles(array $files): void
    {
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

            if (!file_exists($filePath)) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'file not found');

                continue;
            }

            if (0 === (int) filesize($filePath)) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'filesize invalid');

                continue;
            }

            $mimeType = mime_content_type($filePath);

            if (false === $mimeType) {
                continue;
            }

            $tool = $this->getBestToolForMimeType($mimeType);

            if (null === $tool) {
                continue;
            }

            $success = $this->executeOptimization($tool, $filePath);

            if ($success) {
                $this->fileProcessedRepository->updateCompressState($fileId);
            }
        }
    }

    protected function getBestToolForMimeType(string $mimeType): ?string
    {
        $tools = self::MIME_TYPE_TOOLS[$mimeType] ?? [];

        return $this->toolDetection->getFirstAvailable($tools);
    }

    protected function executeOptimization(string $tool, string $filePath): bool
    {
        $toolPath = $this->toolDetection->getToolPath($tool);

        if (null === $toolPath) {
            $this->logger?->warning('Tool path not found', ['tool' => $tool]);

            return false;
        }

        $command = $this->buildCommand($tool, $toolPath, $filePath);

        $output = [];
        CommandUtility::exec($command, $output);

        $this->logger?->debug('Image optimized with local tool', [
            'tool' => $tool,
            'file' => $filePath,
            'command' => $command,
            'output' => implode("\n", $output ?? []),
        ]);

        return true;
    }

    protected function buildCommand(string $tool, string $toolPath, string $filePath): string
    {
        $escapedPath = escapeshellarg($filePath);

        return match ($tool) {
            'jpegoptim' => sprintf(
                '%s --strip-all --all-progressive --max=%d %s',
                $toolPath,
                $this->extensionConfiguration->getJpegQuality(),
                $escapedPath,
            ),
            'pngquant' => sprintf(
                '%s --force --ext .png --quality %d-%d %s',
                $toolPath,
                max(0, $this->extensionConfiguration->getPngQuality() - 15),
                $this->extensionConfiguration->getPngQuality(),
                $escapedPath,
            ),
            'cwebp' => sprintf(
                '%s -q %d %s -o %s',
                $toolPath,
                $this->extensionConfiguration->getWebpQuality(),
                $escapedPath,
                $escapedPath,
            ),
            'avifenc' => sprintf(
                '%s -q %d %s %s',
                $toolPath,
                $this->extensionConfiguration->getWebpQuality(),
                $escapedPath,
                $escapedPath,
            ),
            default => sprintf(
                '%s %s',
                $toolPath,
                sprintf(self::TOOL_COMMANDS[$tool] ?? '%s', $escapedPath),
            ),
        };
    }
}
