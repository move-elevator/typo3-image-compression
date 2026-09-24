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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Command;

use MoveElevator\Typo3ImageCompression\Backup\BackupService;
use MoveElevator\Typo3ImageCompression\Command\PruneBackupsCommand;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * PruneBackupsCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(PruneBackupsCommand::class)]
final class PruneBackupsCommandTest extends TestCase
{
    private BackupService&MockObject $backupServiceMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->backupServiceMock = $this->createMock(BackupService::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $subject = new PruneBackupsCommand($this->backupServiceMock, $this->extensionConfigurationMock);
        $this->commandTester = new CommandTester($subject);
    }

    #[Test]
    public function executeSkipsPruningWhenRetentionIsDisabled(): void
    {
        $this->extensionConfigurationMock->method('getBackupRetentionDays')->willReturn(0);
        $this->backupServiceMock->expects(self::never())->method('prune');

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('disabled', $this->commandTester->getDisplay());
    }

    #[Test]
    public function executePrunesUsingConfiguredRetention(): void
    {
        $this->extensionConfigurationMock->method('getBackupRetentionDays')->willReturn(14);
        $this->backupServiceMock
            ->expects(self::once())
            ->method('prune')
            ->with(14, false)
            ->willReturn(3);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 3 backup file(s) older than 14 day(s).', $this->commandTester->getDisplay());
    }

    #[Test]
    public function executeWithDryRunDoesNotDelete(): void
    {
        $this->extensionConfigurationMock->method('getBackupRetentionDays')->willReturn(14);
        $this->backupServiceMock
            ->expects(self::once())
            ->method('prune')
            ->with(14, true)
            ->willReturn(2);

        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Would delete 2 backup file(s)', $this->commandTester->getDisplay());
    }
}
