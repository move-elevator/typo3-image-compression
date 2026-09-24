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

use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

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

    /**
     * Standard EXIF GPS position tags. Blanked explicitly whenever the
     * EXIF/IPTC block as a whole is kept, since plain "convert" has no
     * per-tag strip flag and GPS location must never be preserved.
     */
    private const GPS_EXIF_TAGS = [
        'GPSVersionID',
        'GPSLatitudeRef',
        'GPSLatitude',
        'GPSLongitudeRef',
        'GPSLongitude',
        'GPSAltitudeRef',
        'GPSAltitude',
        'GPSTimeStamp',
        'GPSDateStamp',
        'GPSMapDatum',
    ];

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly FileProcessedRepository $fileProcessedRepository,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly StorageRepository $storageRepository,
        protected readonly ToolDetection $toolDetection,
        protected readonly BackupService $backupService,
    ) {}

    public function getProviderIdentifier(): string
    {
        return self::PROVIDER_IDENTIFIER;
    }

    public function compress(File|FileInterface $file): CompressionOutcome
    {
        if (!$file instanceof File) {
            return CompressionOutcome::Skipped;
        }

        // Check if file is in excluded folder
        if ($this->isFileInExcludeFolder($file)) {
            return CompressionOutcome::Skipped;
        }

        $mimeType = strtolower($file->getMimeType());

        // Check if MIME type is configured for compression AND supported by this provider
        if (!in_array($mimeType, $this->extensionConfiguration->getMimeTypes(), true)) {
            return CompressionOutcome::Skipped;
        }

        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            return CompressionOutcome::Skipped;
        }

        if (!$this->isLocalStorage($file->getStorage())) {
            $this->rejectUnsupportedStorage($file);

            return CompressionOutcome::Failed;
        }

        $filePath = $this->getAbsoluteFilePath($file);

        if (!file_exists($filePath) || 0 === (int) filesize($filePath)) {
            return CompressionOutcome::Failed;
        }

        $originalFileSize = (int) filesize($filePath);
        $processor = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? 'ImageMagick';
        $this->maybeBackupOriginal($file, $filePath);
        $quality = $this->getQualityForMimeType($mimeType);
        $sourceQuality = $this->detectSourceJpegQuality($filePath);

        if (null !== $sourceQuality && $quality >= $sourceQuality) {
            $compressInfo = $this->buildSkippedInfo(self::PROVIDER_IDENTIFIER, $originalFileSize, $processor);
            $this->markFileAsOptimal($file, $compressInfo);
            $this->logger?->info('Image already at or above target quality, kept original', [
                'file' => $file->getIdentifier(),
                'processor' => $processor,
                'targetQuality' => $quality,
                'sourceQuality' => $sourceQuality,
            ]);
            $this->addFlashMessage('alreadyOptimal', [], ContextualFeedbackSeverity::INFO);

            return CompressionOutcome::Skipped;
        }

        $outcome = $this->compressToTempAndReplace(
            $filePath,
            fn (string $tempPath): bool => $this->compressWithGraphicsProcessor($tempPath, $mimeType),
        );

        if (null === $outcome) {
            return CompressionOutcome::Failed;
        }

        if (!$outcome['replaced']) {
            $compressInfo = $this->buildSkippedInfo(self::PROVIDER_IDENTIFIER, $outcome['originalSize'], $processor);
            $this->markFileAsOptimal($file, $compressInfo);
            $this->logger?->info('Image already optimal, kept original', [
                'file' => $file->getIdentifier(),
                'processor' => $processor,
                'originalSize' => $outcome['originalSize'],
                'attemptedSize' => $outcome['newSize'],
            ]);
            $this->addFlashMessage('alreadyOptimal', [], ContextualFeedbackSeverity::INFO);

            return CompressionOutcome::Skipped;
        }

        $savedPercent = $this->calculateSavedPercent($outcome['originalSize'], $outcome['newSize']);
        $compressInfo = $this->buildCompressInfo(self::PROVIDER_IDENTIFIER, $outcome['originalSize'], $outcome['newSize'], $processor);
        $this->markFileAsCompressed($file, $compressInfo);
        $this->updateFileInformation($file);

        if ($savedPercent > 0) {
            $this->logger?->info('Image compressed', [
                'file' => $file->getIdentifier(),
                'processor' => $processor,
                'originalSize' => $outcome['originalSize'],
                'newSize' => $outcome['newSize'],
                'savedPercent' => $savedPercent,
            ]);
            $this->addFlashMessage('success', [$savedPercent.'%'], ContextualFeedbackSeverity::INFO);
        }

        return CompressionOutcome::Compressed;
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

            if (!$this->isLocalStorage($storage)) {
                $this->fileProcessedRepository->updateCompressState($fileId, 0, 'unsupported storage driver: '.$storage->getDriverType());

                continue;
            }

            $filePath = $this->resolveProcessedFilePath($storage, (string) $file['identifier']);

            if (null === $filePath || !file_exists($filePath)) {
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

            if ($this->compressWithGraphicsProcessor($filePath, $mimeType)) {
                $this->fileProcessedRepository->updateCompressState($fileId);
            }
        }
    }

    /**
     * Detects the JPEG source's own encoding quality via `identify -format "%Q"`.
     *
     * Returns null when the `identify` binary is unavailable or its output
     * cannot be parsed. Callers must treat null as "unknown" and fall back
     * to the general compress-and-compare safety net, not as "quality 0".
     */
    protected function detectSourceJpegQuality(string $filePath): ?int
    {
        $identifyPath = $this->toolDetection->getToolPath('identify');

        if (null === $identifyPath) {
            return null;
        }

        $process = new Process([$identifyPath, '-format', '%Q', $filePath]);
        $process->setTimeout($this->extensionConfiguration->getCommandTimeout());

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->logger?->warning('JPEG source quality detection timed out', [
                'file' => $filePath,
                'command' => $process->getCommandLine(),
                'timeout' => $this->extensionConfiguration->getCommandTimeout(),
            ]);

            return null;
        }

        if (!$process->isSuccessful()) {
            return null;
        }

        $quality = (int) trim($process->getOutput());

        return $quality > 0 ? $quality : null;
    }

    protected function compressWithGraphicsProcessor(string $filePath, string $mimeType): bool
    {
        $processor = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? 'ImageMagick';
        $quality = $this->getQualityForMimeType($mimeType);
        $metadataArgument = $this->getMetadataArgument();

        if ('GraphicsMagick' === $processor) {
            $binary = $this->toolDetection->getToolPath('graphicsmagick');

            if (null === $binary) {
                $this->logger?->warning('GraphicsMagick not found', ['file' => $filePath]);

                return false;
            }

            $command = [$binary, 'convert', '-quality', (string) $quality, ...$metadataArgument, $filePath, $filePath];
        } else {
            $binary = $this->toolDetection->getToolPath('imagemagick');

            if (null === $binary) {
                $this->logger?->warning('ImageMagick not found', ['file' => $filePath]);

                return false;
            }

            // ImageMagick v7+ uses "magick convert", v6 uses "convert" directly
            $command = str_ends_with($binary, 'magick')
                ? [$binary, 'convert', '-quality', (string) $quality, ...$metadataArgument, $filePath, $filePath]
                : [$binary, '-quality', (string) $quality, ...$metadataArgument, $filePath, $filePath];
        }

        $process = new Process($command);
        $process->setTimeout($this->extensionConfiguration->getCommandTimeout());

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->logger?->warning('Image compression timed out', [
                'processor' => $processor,
                'file' => $filePath,
                'command' => $process->getCommandLine(),
                'timeout' => $this->extensionConfiguration->getCommandTimeout(),
            ]);

            return false;
        }

        if (!$process->isSuccessful()) {
            $this->logger?->warning('Image compression failed', [
                'processor' => $processor,
                'file' => $filePath,
                'quality' => $quality,
                'exitCode' => $process->getExitCode(),
                'output' => $process->getErrorOutput().$process->getOutput(),
            ]);

            return false;
        }

        $this->logger?->debug('Image compressed with basic processor', [
            'processor' => $processor,
            'file' => $filePath,
            'quality' => $quality,
            'output' => $process->getOutput(),
        ]);

        return true;
    }

    protected function getQualityForMimeType(string $mimeType): int
    {
        return match ($mimeType) {
            'image/jpeg' => $this->extensionConfiguration->getJpegQuality(),
            default => 85,
        };
    }

    /**
     * Builds the ImageMagick/GraphicsMagick metadata argument from configuration.
     *
     * Plain "convert" has no per-tag strip flag, only "strip everything" or
     * "strip all profiles except one". Preserving copyright or the creation
     * date therefore keeps the whole EXIF/IPTC block rather than stripping
     * selectively, so the GPS position tags are explicitly blanked in that
     * case instead: GPS location must never be preserved regardless of the
     * other settings.
     *
     * @return array<int, string>
     */
    protected function getMetadataArgument(): array
    {
        if ($this->extensionConfiguration->isPreserveCopyright() || $this->extensionConfiguration->isPreserveCreationDate()) {
            $arguments = [];

            foreach (self::GPS_EXIF_TAGS as $tag) {
                $arguments[] = '-set';
                $arguments[] = sprintf('exif:%s', $tag);
                $arguments[] = '';
            }

            return $arguments;
        }

        if ($this->extensionConfiguration->isPreserveColorProfile()) {
            return ['+profile', '!icc,*'];
        }

        return ['-strip'];
    }
}
