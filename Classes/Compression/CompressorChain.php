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

use TYPO3\CMS\Core\Resource\{File, FileInterface};

use function strtolower;

/**
 * CompressorChain.
 *
 * Walks an ordered list of compressors and delegates to the first one that
 * both {@see CompressorInterface::supports()} the file's MIME type and, where
 * it implements {@see AvailabilityAwareInterface}, reports itself available.
 *
 * When every supporting compressor reports itself unavailable (e.g. every
 * configured provider's quota is exhausted), the chain still delegates to the
 * last one instead of silently doing nothing, so the resulting failure is
 * recorded on the file the same way a single, unavailable provider already
 * behaves today.
 *
 * `compressProcessedFiles()` is deliberately not chain-routed: unlike
 * `compress()`, TYPO3 does not carry a processed file's MIME type on the
 * record, so choosing a provider would require re-resolving and reading the
 * file from disk before delegation, on what is already the quota-saving,
 * lower-priority path (the CLI command's own `--include-processed` flag
 * documentation recommends omitting it). It always uses the first configured
 * (primary) provider.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class CompressorChain implements CompressorInterface
{
    /**
     * @param non-empty-list<CompressorInterface> $compressors In fallback order
     */
    public function __construct(private array $compressors) {}

    public function supports(string $mimeType): bool
    {
        foreach ($this->compressors as $compressor) {
            if ($compressor->supports($mimeType)) {
                return true;
            }
        }

        return false;
    }

    public function compress(File|FileInterface $file): void
    {
        $this->resolveFor(strtolower($file->getMimeType()))?->compress($file);
    }

    public function compressProcessedFiles(array $files): void
    {
        $this->compressors[0]->compressProcessedFiles($files);
    }

    public function getProviderIdentifier(): string
    {
        return 'chain';
    }

    private function resolveFor(string $mimeType): ?CompressorInterface
    {
        $supporting = [];

        foreach ($this->compressors as $compressor) {
            if ($compressor->supports($mimeType)) {
                $supporting[] = $compressor;
            }
        }

        foreach ($supporting as $compressor) {
            if (!$compressor instanceof AvailabilityAwareInterface || $compressor->isAvailable()) {
                return $compressor;
            }
        }

        return $supporting[array_key_last($supporting)] ?? null;
    }
}
