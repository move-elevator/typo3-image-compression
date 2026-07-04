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

use MoveElevator\Typo3ImageCompression\Command\CompressImageCommand;
use MoveElevator\Typo3ImageCompression\Compression\CompressorInterface;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository, FileStorageRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * CompressImageCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressImageCommand::class)]
final class CompressImageCommandTest extends TestCase
{
    private CompressImageCommand $subject;
    private FileStorageRepository&MockObject $fileStorageRepositoryMock;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private ResourceFactory $resourceFactory;
    private CompressorInterface&MockObject $compressorMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;

    protected function setUp(): void
    {
        $this->fileStorageRepositoryMock = $this->createMock(FileStorageRepository::class);
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        // ResourceFactory is a readonly class and cannot be doubled by PHPUnit;
        // the code paths under test never touch it, so an instance created
        // without invoking the constructor is sufficient to satisfy the type.
        $this->resourceFactory = (new ReflectionClass(ResourceFactory::class))->newInstanceWithoutConstructor();
        $this->compressorMock = $this->createMock(CompressorInterface::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $this->subject = new CompressImageCommand(
            $this->fileStorageRepositoryMock,
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->resourceFactory,
            $this->compressorMock,
            $this->extensionConfigurationMock,
        );
    }

    #[Test]
    public function executeReturnsSuccessWhenThereIsNothingToCompress(): void
    {
        $emptyStorages = $this->createMock(QueryResultInterface::class);
        $emptyStorages->method('valid')->willReturn(false);
        $this->fileStorageRepositoryMock->method('findAll')->willReturn($emptyStorages);

        $exitCode = $this->invokeExecute(includeProcessed: false, retryErrors: false, limit: 100);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    #[Test]
    public function executeDoesNotQueryStoragesWhenLimitIsZero(): void
    {
        // With a limit of 0 no original files may be looked up at all.
        $this->fileStorageRepositoryMock->expects(self::never())->method('findAll');

        $exitCode = $this->invokeExecute(includeProcessed: false, retryErrors: false, limit: 0);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    private function invokeExecute(bool $includeProcessed, bool $retryErrors, int $limit): int
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getArgument')->with('limit')->willReturn($limit);
        $input->method('getOption')->willReturnMap([
            ['include-processed', $includeProcessed],
            ['retry-errors', $retryErrors],
        ]);

        $output = $this->createMock(OutputInterface::class);

        $method = new ReflectionMethod($this->subject, 'execute');

        /** @var int $result */
        $result = $method->invoke($this->subject, $input, $output);

        return $result;
    }
}
