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

use MoveElevator\Typo3ImageCompression\Backup\RestoreService;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

use function sprintf;

/**
 * RestoreImageCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class RestoreImageCommand extends Command
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly RestoreService $restoreService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'uid',
            InputArgument::OPTIONAL,
            'UID of the sys_file record to restore from its backup',
        );
        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Restore every file that currently has a backup',
        );
    }

    /**
     * @throws InvalidQueryException
     * @throws NoSuchCacheGroupException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $uid = (int) $input->getArgument('uid');
        $all = (bool) $input->getOption('all');

        if (!$all && 0 === $uid) {
            $output->writeln('<error>Provide a file UID or use --all.</error>');

            return Command::INVALID;
        }

        $fileUids = $all ? $this->collectAllBackedUpFileUids() : [$uid];
        $restored = 0;
        $failed = 0;

        foreach ($fileUids as $fileUid) {
            if ($this->restoreService->restoreByUid($fileUid)) {
                $output->writeln(sprintf('Restored file %d', $fileUid));
                ++$restored;
            } else {
                $output->writeln(sprintf('<error>No backup found for file %d</error>', $fileUid));
                ++$failed;
            }
        }

        if ($restored > 0) {
            GeneralUtility::makeInstance(CacheManager::class)->flushCachesInGroup('pages');
        }

        $output->writeln(sprintf('Restored: %d, Failed: %d', $restored, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return int[]
     *
     * @throws InvalidQueryException
     */
    private function collectAllBackedUpFileUids(): array
    {
        $uids = [];

        foreach ($this->fileRepository->findAllWithBackup() as $file) {
            $uid = $file->getUid();

            if (null !== $uid) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }
}
