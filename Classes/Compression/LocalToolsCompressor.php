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
 * LocalToolsCompressor.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LocalToolsCompressor implements CompressorInterface, MimeTypeAwareInterface, LoggerAwareInterface, SingletonInterface
{
    use CompressorTrait;
    use FlashMessageTrait;
    use LoggerAwareTrait;

    private const PROVIDER_IDENTIFIER = 'local-tools';

    /**
     * Tool argument lists (file path is appended by buildCommand()).
     * Tools with configurable quality use getToolCommand() method instead.
     */
    private const TOOL_COMMANDS = [
        'optipng' => ['-o2', '-strip', 'all'],
        'gifsicle' => ['--batch', '-O2'],
        // svgo overwrites its input in place when no -o is given, same as every other tool here.
        'svgo' => ['--quiet'],
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
        'image/svg+xml' => ['svgo'],
    ];

    public function __construct(
        protected readonly FileRepository $fileRepository,
        protected readonly FileProcessedRepository $fileProcessedRepository,
        protected readonly ExtensionConfiguration $extensionConfiguration,
        protected readonly StorageRepository $storageRepository,
        protected readonly ToolDetection $toolDetection,
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

        // Check if MIME type is configured for compression
        if (!$this->isMimeTypeSupported($mimeType)) {
            return CompressionOutcome::Skipped;
        }

        $tool = $this->getBestToolForMimeType($mimeType);

        if (null === $tool) {
            $this->logger?->info('No suitable tool available for MIME type', [
                'mimeType' => $mimeType,
                'file' => $file->getIdentifier(),
            ]);

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

        $outcome = $this->compressToTempAndReplace(
            $filePath,
            fn (string $tempPath): bool => $this->executeOptimization($tool, $tempPath),
        );

        if (null === $outcome) {
            return CompressionOutcome::Failed;
        }

        if (!$outcome['replaced']) {
            $compressInfo = $this->buildSkippedInfo(self::PROVIDER_IDENTIFIER, $outcome['originalSize'], $tool);
            $this->markFileAsOptimal($file, $compressInfo);
            $this->logger?->info('Image already optimal, kept original', [
                'file' => $file->getIdentifier(),
                'tool' => $tool,
                'originalSize' => $outcome['originalSize'],
                'attemptedSize' => $outcome['newSize'],
            ]);
            $this->addFlashMessage('alreadyOptimal', [], ContextualFeedbackSeverity::INFO);

            return CompressionOutcome::Skipped;
        }

        $savedPercent = $this->calculateSavedPercent($outcome['originalSize'], $outcome['newSize']);
        $compressInfo = $this->buildCompressInfo(self::PROVIDER_IDENTIFIER, $outcome['originalSize'], $outcome['newSize'], $tool);
        $this->markFileAsCompressed($file, $compressInfo);
        $this->updateFileInformation($file);

        if ($savedPercent > 0) {
            $this->logger?->info('Image compressed', [
                'file' => $file->getIdentifier(),
                'tool' => $tool,
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

    /**
     * Returns the configured `mimeTypes` plus `image/svg+xml` when svgo is
     * detected, so consumers outside `compress()` (the CLI batch command,
     * the statistics report) apply the same effective allowlist as the
     * zero-configuration SVG support above.
     *
     * @return string[]
     */
    public function getSupportedMimeTypes(): array
    {
        $mimeTypes = $this->extensionConfiguration->getMimeTypes();

        if (!in_array('image/svg+xml', $mimeTypes, true) && $this->toolDetection->isAvailable('svgo')) {
            $mimeTypes[] = 'image/svg+xml';
        }

        return $mimeTypes;
    }

    /**
     * SVG is deliberately excluded from the extension's mimeTypes default:
     * unlike the raster formats, it is only compressible once svgo is
     * actually installed. Treating it as supported the moment svgo is
     * detected means it works with zero configuration where the tool is
     * present, and stays inert everywhere else.
     */
    protected function isMimeTypeSupported(string $mimeType): bool
    {
        return in_array($mimeType, $this->getSupportedMimeTypes(), true);
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
        $process = new Process($command);
        $process->setTimeout($this->extensionConfiguration->getCommandTimeout());

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->logger?->warning('Image optimization timed out', [
                'tool' => $tool,
                'file' => $filePath,
                'command' => $process->getCommandLine(),
                'timeout' => $this->extensionConfiguration->getCommandTimeout(),
            ]);

            return false;
        }

        if (!$process->isSuccessful()) {
            $this->logger?->warning('Image optimization failed', [
                'tool' => $tool,
                'file' => $filePath,
                'command' => $process->getCommandLine(),
                'exitCode' => $process->getExitCode(),
                'output' => $process->getErrorOutput().$process->getOutput(),
            ]);

            return false;
        }

        $this->logger?->debug('Image optimized with local tool', [
            'tool' => $tool,
            'file' => $filePath,
            'command' => $process->getCommandLine(),
            'output' => $process->getOutput(),
        ]);

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function buildCommand(string $tool, string $toolPath, string $filePath): array
    {
        return match ($tool) {
            'jpegoptim' => [
                $toolPath,
                ...$this->getJpegoptimStripArgument(),
                '--all-progressive',
                sprintf('--max=%d', $this->extensionConfiguration->getJpegQuality()),
                $filePath,
            ],
            'pngquant' => [
                $toolPath,
                '--force',
                '--ext', '.png',
                '--quality', sprintf(
                    '%d-%d',
                    max(0, $this->extensionConfiguration->getPngQuality() - 15),
                    $this->extensionConfiguration->getPngQuality(),
                ),
                $filePath,
            ],
            'cwebp' => [
                $toolPath,
                '-q', (string) $this->extensionConfiguration->getWebpQuality(),
                $filePath,
                '-o', $filePath,
            ],
            'avifenc' => [
                $toolPath,
                '-q', (string) $this->extensionConfiguration->getWebpQuality(),
                $filePath,
                $filePath,
            ],
            default => [
                $toolPath,
                ...(self::TOOL_COMMANDS[$tool] ?? []),
                $filePath,
            ],
        };
    }

    /**
     * Builds the jpegoptim strip flags from configuration.
     *
     * The color profile has its own block (`--strip-icc`) and is preserved
     * independently. Copyright and creation date, however, both live inside
     * the same EXIF/IPTC blocks (alongside GPS): jpegoptim can only strip
     * `--strip-exif`/`--strip-iptc` as whole blocks, not individual tags, so
     * enabling either `preserveCopyright` or `preserveCreationDate` keeps
     * the whole EXIF/IPTC data, including the other field and GPS, rather
     * than that one field alone (see README.md's provider support table).
     * Comments carry none of that data and are always stripped.
     *
     * @return array<int, string>
     */
    protected function getJpegoptimStripArgument(): array
    {
        $preserveCopyrightOrDate = $this->extensionConfiguration->isPreserveCopyright()
            || $this->extensionConfiguration->isPreserveCreationDate();

        $flags = ['--strip-com', '--strip-xmp'];

        if (!$preserveCopyrightOrDate) {
            $flags[] = '--strip-exif';
            $flags[] = '--strip-iptc';
        }

        if (!$this->extensionConfiguration->isPreserveColorProfile()) {
            $flags[] = '--strip-icc';
        }

        return $flags;
    }
}
