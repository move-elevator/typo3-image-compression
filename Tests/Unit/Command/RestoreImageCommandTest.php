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

use MoveElevator\Typo3ImageCompression\Backup\RestoreService;
use MoveElevator\Typo3ImageCompression\Command\RestoreImageCommand;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * RestoreImageCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreImageCommand::class)]
final class RestoreImageCommandTest extends TestCase
{
    private FileRepository&MockObject $fileRepositoryMock;
    private RestoreService&MockObject $restoreServiceMock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->restoreServiceMock = $this->createMock(RestoreService::class);

        $subject = new RestoreImageCommand($this->fileRepositoryMock, $this->restoreServiceMock);
        $this->commandTester = new CommandTester($subject);
    }

    #[Test]
    public function executeFailsWhenNeitherUidNorAllIsGiven(): void
    {
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Provide a file UID or use --all.', $this->commandTester->getDisplay());
    }

    #[Test]
    public function executeFailsWhenUidHasNoBackup(): void
    {
        $this->restoreServiceMock->method('restoreByUid')->with(42)->willReturn(false);

        $exitCode = $this->commandTester->execute(['uid' => 42]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('No backup found for file 42', $this->commandTester->getDisplay());
        self::assertStringContainsString('Restored: 0, Failed: 1', $this->commandTester->getDisplay());
    }

    // The success path (which triggers CacheManager::flushCachesInGroup())
    // needs a real "pages" cache configuration that only exists with a full
    // TYPO3 bootstrap; it is covered by
    // Tests/Functional/Command/RestoreImageCommandTest.php instead.

    #[Test]
    public function executeWithAllAndNoBackedUpFilesSucceedsWithNothingToDo(): void
    {
        $emptyResult = $this->createMock(QueryResultInterface::class);
        $emptyResult->method('valid')->willReturn(false);
        $this->fileRepositoryMock->method('findAllWithBackup')->willReturn($emptyResult);
        $this->restoreServiceMock->expects(self::never())->method('restoreByUid');

        $exitCode = $this->commandTester->execute(['--all' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Restored: 0, Failed: 0', $this->commandTester->getDisplay());
    }
}
