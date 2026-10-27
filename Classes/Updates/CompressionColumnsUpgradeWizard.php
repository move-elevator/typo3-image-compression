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

namespace MoveElevator\Typo3ImageCompression\Updates;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Updates\{DatabaseUpdatedPrerequisite, UpgradeWizardInterface};

/**
 * CompressionColumnsUpgradeWizard.
 *
 * `compress_info` (a single formatted string) was replaced with structured
 * columns (compress_provider, compress_tool, compress_original_size,
 * compress_size, compress_tstamp). Re-parsing the legacy strings back into
 * the new columns is not attempted (the old format is not reliably
 * parseable); instead, files already marked as compressed get their new
 * columns reset to "unknown" while the `compressed` flag itself is left
 * untouched.
 *
 * Title and description are plain English strings: the Install Tool and the
 * CLI have no `$GLOBALS['LANG']`, as with the core wizards.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final readonly class CompressionColumnsUpgradeWizard implements UpgradeWizardInterface
{
    private const UNKNOWN_PROVIDER = 'unknown';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getTitle(): string
    {
        return 'Reset structured image compression columns for legacy compressed files';
    }

    public function getDescription(): string
    {
        return 'Earlier versions of this extension stored the compression result as a single formatted string (compress_info). '
            .'That column has been replaced with structured columns (provider, tool, original size, compressed size, timestamp). '
            .'Re-parsing the legacy strings is not attempted; this wizard resets the new columns to "unknown" for files '
            .'that are already marked as compressed, leaving the compressed flag itself untouched.';
    }

    public function updateNecessary(): bool
    {
        return $this->countLegacyRows() > 0;
    }

    public function executeUpdate(): bool
    {
        $connection = $this->connectionPool->getConnectionForTable('sys_file');

        // compress_tstamp is deliberately left at its column default (0,
        // "unknown"): the actual compression time of a legacy row was never
        // recorded, and persisting time() here would make every migrated
        // file appear to have been compressed on upgrade day.
        $connection->update(
            'sys_file',
            [
                'compress_provider' => self::UNKNOWN_PROVIDER,
            ],
            [
                'compressed' => 1,
                'compress_provider' => '',
            ],
        );

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    private function countLegacyRows(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');

        $count = $queryBuilder
            ->count('uid')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('compressed', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('compress_provider', $queryBuilder->createNamedParameter('')),
            )
            ->executeQuery()
            ->fetchOne();

        return (int) $count;
    }
}
