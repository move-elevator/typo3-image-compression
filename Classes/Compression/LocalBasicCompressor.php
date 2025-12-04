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
 * LocalBasicCompressor.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LocalBasicCompressor implements CompressorInterface, LoggerAwareInterface, SingletonInterface
{
    use CompressorTrait;
    use FlashMessageTrait;
    use LoggerAwareTrait;

    private const PROVIDER_IDENTIFIER = 'local-basic';

    /**
     * PNG and GIF are excluded because ImageMagick/GraphicsMagick typically
     * increases file size when reprocessing these formats. Use local-tools
     * provider with optipng/pngquant for PNG compression.
     */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
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

        // Check if MIME type is configured for compression AND supported by this provider
        if (!in_array($mimeType, $this->extensionConfiguration->getMimeTypes(), true)) {
            return;
        }

        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            return;
        }

        $filePath = $this->getAbsoluteFilePath($file);

        if (!file_exists($filePath) || 0 === (int) filesize($filePath)) {
            return;
        }

        $originalFileSize = (int) filesize($filePath);
        $this->compressWithGraphicsProcessor($filePath, $mimeType);
        $this->markFileAsCompressed($file);
        $this->updateFileInformation($file);

        // Log compression result and show flash message
        clearstatcache(true, $filePath);
        $newFileSize = (int) filesize($filePath);
        $savedPercent = $this->calculateSavedPercent($originalFileSize, $newFileSize);
        if ($savedPercent > 0) {
            $this->logger?->info('Image compressed', [
                'file' => $file->getIdentifier(),
                'processor' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? 'ImageMagick',
                'originalSize' => $originalFileSize,
                'newSize' => $newFileSize,
                'savedPercent' => $savedPercent,
            ]);
            $this->addFlashMessage('success', [$savedPercent.'%'], ContextualFeedbackSeverity::INFO);
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

            if (false === $mimeType || !in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
                continue;
            }

            $this->compressWithGraphicsProcessor($filePath, $mimeType);
            $this->fileProcessedRepository->updateCompressState($fileId);
        }
    }

    protected function compressWithGraphicsProcessor(string $filePath, string $mimeType): void
    {
        $processor = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? 'ImageMagick';
        $quality = $this->getQualityForMimeType($mimeType);

        if ('GraphicsMagick' === $processor) {
            $binary = $this->toolDetection->getToolPath('graphicsmagick');

            if (null === $binary) {
                $this->logger?->warning('GraphicsMagick not found', ['file' => $filePath]);

                return;
            }

            $command = sprintf(
                '%s convert -quality %d -strip %s %s',
                $binary,
                $quality,
                escapeshellarg($filePath),
                escapeshellarg($filePath),
            );
        } else {
            $binary = $this->toolDetection->getToolPath('imagemagick');

            if (null === $binary) {
                $this->logger?->warning('ImageMagick not found', ['file' => $filePath]);

                return;
            }

            // ImageMagick v7+ uses "magick convert", v6 uses "convert" directly
            $subCommand = str_ends_with($binary, 'magick') ? 'convert ' : '';

            $command = sprintf(
                '%s %s-quality %d -strip %s %s',
                $binary,
                $subCommand,
                $quality,
                escapeshellarg($filePath),
                escapeshellarg($filePath),
            );
        }

        $output = [];
        CommandUtility::exec($command, $output);

        $this->logger?->debug('Image compressed with basic processor', [
            'processor' => $processor,
            'file' => $filePath,
            'quality' => $quality,
            'output' => implode("\n", $output ?? []),
        ]);
    }

    protected function getQualityForMimeType(string $mimeType): int
    {
        return match ($mimeType) {
            'image/jpeg' => $this->extensionConfiguration->getJpegQuality(),
            default => 85,
        };
    }
}
