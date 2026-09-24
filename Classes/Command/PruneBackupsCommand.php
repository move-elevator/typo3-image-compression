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

use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * PruneBackupsCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class PruneBackupsCommand extends Command
{
    public function __construct(
        private readonly BackupService $backupService,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'List how many backups would be deleted without deleting them',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retentionDays = $this->extensionConfiguration->getBackupRetentionDays();

        if (0 === $retentionDays) {
            $output->writeln('Backup retention is disabled (0 days). Nothing to prune.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $deleted = $this->backupService->prune($retentionDays, $dryRun);

        $output->writeln(sprintf(
            '%s %d backup file(s) older than %d day(s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $deleted,
            $retentionDays,
        ));

        return Command::SUCCESS;
    }
}
