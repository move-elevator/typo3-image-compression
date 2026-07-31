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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Utility;

use MoveElevator\Typo3ImageCompression\Utility\CompressionResultHandler;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception as Typo3CoreException;
use TYPO3\CMS\Core\Messaging\{FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CompressionResultHandlerTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionResultHandler::class)]
final class CompressionResultHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function outputToConsoleWritesNoFilesMessageWhenStatsAreZero(): void
    {
        $output = new BufferedOutput();

        CompressionResultHandler::outputToConsole($output, $this->zeroStats());

        self::assertStringContainsString('No files to compress.', $output->fetch());
    }

    #[Test]
    public function outputToConsoleWritesOriginalStatsOnly(): void
    {
        $output = new BufferedOutput();
        $stats = [
            'original' => ['total' => 5, 'success' => 4, 'errors' => 1],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ];

        CompressionResultHandler::outputToConsole($output, $stats);
        $content = $output->fetch();

        self::assertStringContainsString('Compression Summary', $content);
        self::assertStringContainsString('Original files: 4/5 compressed, 1 errors', $content);
        self::assertStringNotContainsString('Processed files:', $content);
        self::assertStringContainsString('Total: 4/5 compressed, 1 errors', $content);
    }

    #[Test]
    public function outputToConsoleWritesProcessedStatsOnly(): void
    {
        $output = new BufferedOutput();
        $stats = [
            'original' => ['total' => 0, 'success' => 0, 'errors' => 0],
            'processed' => ['total' => 3, 'success' => 3, 'errors' => 0],
        ];

        CompressionResultHandler::outputToConsole($output, $stats);
        $content = $output->fetch();

        self::assertStringNotContainsString('Original files:', $content);
        self::assertStringContainsString('Processed files: 3/3 compressed, 0 errors', $content);
        self::assertStringContainsString('Total: 3/3 compressed, 0 errors', $content);
    }

    #[Test]
    public function outputToConsoleWritesBothStats(): void
    {
        $output = new BufferedOutput();
        $stats = [
            'original' => ['total' => 5, 'success' => 4, 'errors' => 1],
            'processed' => ['total' => 3, 'success' => 3, 'errors' => 0],
        ];

        CompressionResultHandler::outputToConsole($output, $stats);
        $content = $output->fetch();

        self::assertStringContainsString('Original files: 4/5 compressed, 1 errors', $content);
        self::assertStringContainsString('Processed files: 3/3 compressed, 0 errors', $content);
        self::assertStringContainsString('Total: 7/8 compressed, 1 errors', $content);
    }

    #[Test]
    public function addFlashMessageDoesNothingWhenBackendUserIsNotLoggedIn(): void
    {
        $contextMock = $this->createMock(Context::class);
        $contextMock->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with('backend.user', 'isLoggedIn')
            ->willReturn(false);

        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        // If it proceeded further, makeInstance(FlashMessage::class) would be
        // attempted, which would require a queued instance we did not provide.
        CompressionResultHandler::addFlashMessage([
            'original' => ['total' => 5, 'success' => 5, 'errors' => 0],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ]);
    }

    #[Test]
    public function addFlashMessageDoesNothingWhenContextThrowsException(): void
    {
        $contextMock = $this->createMock(Context::class);
        $contextMock->expects(self::once())
            ->method('getPropertyFromAspect')
            ->willThrowException(new Typo3CoreException('aspect not found'));

        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        CompressionResultHandler::addFlashMessage([
            'original' => ['total' => 5, 'success' => 5, 'errors' => 0],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ]);
    }

    #[Test]
    public function addFlashMessageDoesNothingWhenStatsAreZero(): void
    {
        $this->expectNotToPerformAssertions();

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getPropertyFromAspect')->willReturn(true);

        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        CompressionResultHandler::addFlashMessage($this->zeroStats());
    }

    #[Test]
    public function addFlashMessageEnqueuesMessageWhenLoggedInAndStatsPresentWithoutErrors(): void
    {
        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getPropertyFromAspect')->willReturn(true);
        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        $queueMock = $this->createMock(FlashMessageQueue::class);
        $queueMock->expects(self::once())->method('addMessage');

        $flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $flashMessageServiceMock->expects(self::once())
            ->method('getMessageQueueByIdentifier')
            ->willReturn($queueMock);

        GeneralUtility::setSingletonInstance(FlashMessageService::class, $flashMessageServiceMock);

        CompressionResultHandler::addFlashMessage([
            'original' => ['total' => 5, 'success' => 5, 'errors' => 0],
            'processed' => ['total' => 3, 'success' => 3, 'errors' => 0],
        ]);
    }

    #[Test]
    public function addFlashMessageEnqueuesWarningSeverityWhenErrorsPresent(): void
    {
        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getPropertyFromAspect')->willReturn(true);
        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        $queueMock = $this->createMock(FlashMessageQueue::class);
        $queueMock->expects(self::once())->method('addMessage');

        $flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $flashMessageServiceMock->expects(self::once())
            ->method('getMessageQueueByIdentifier')
            ->willReturn($queueMock);

        GeneralUtility::setSingletonInstance(FlashMessageService::class, $flashMessageServiceMock);

        CompressionResultHandler::addFlashMessage([
            'original' => ['total' => 5, 'success' => 4, 'errors' => 1],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ]);
    }

    /**
     * @return array{original: array{total: int, success: int, errors: int}, processed: array{total: int, success: int, errors: int}}
     */
    private function zeroStats(): array
    {
        return [
            'original' => ['total' => 0, 'success' => 0, 'errors' => 0],
            'processed' => ['total' => 0, 'success' => 0, 'errors' => 0],
        ];
    }
}
