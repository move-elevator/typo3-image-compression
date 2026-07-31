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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression;

use MoveElevator\Typo3ImageCompression\Compression\CompressorTrait;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Resource\{File, ResourceStorage};
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function sprintf;

/**
 * CompressorTraitTestSubject.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface CompressorTraitTestSubject
{
    public function isFileInExcludeFolder(File $file): bool;

    public function getAbsoluteFilePath(File $file): string;

    public function markFileAsCompressed(File $file, string $compressInfo = ''): void;

    public function buildCompressInfo(string $provider, int $originalSize, int $newSize, ?string $tool = null): string;

    public function formatFileSize(int $bytes): string;

    public function resolveProcessedFilePath(ResourceStorage $storage, string $identifier): ?string;

    public function buildStoragePath(string $publicPath, string $basePath, string $identifier): ?string;

    public function updateFileInformation(File $file): void;

    public function calculateSavedPercent(int $originalSize, int $newSize): int;
}

/**
 * CompressorTraitTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressorTrait::class)]
final class CompressorTraitTest extends TestCase
{
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private FileRepository&MockObject $fileRepositoryMock;
    private CompressorTraitTestSubject $subject;

    protected function setUp(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            '/var/www',
            '/var/www/public',
            '/var/www/var',
            '/var/www/config',
            '/var/www/public/index.php',
            'UNIX',
        );

        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->fileRepositoryMock = $this->createMock(FileRepository::class);

        $this->subject = new class implements CompressorTraitTestSubject {
            use CompressorTrait {
                isFileInExcludeFolder as public;
                getAbsoluteFilePath as public;
                markFileAsCompressed as public;
                buildCompressInfo as public;
                formatFileSize as public;
                resolveProcessedFilePath as public;
                buildStoragePath as public;
                updateFileInformation as public;
                calculateSavedPercent as public;
            }

            public ExtensionConfiguration $extensionConfiguration;
            public FileRepository $fileRepository;
        };

        $this->subject->extensionConfiguration = $this->extensionConfigurationMock;
        $this->subject->fileRepository = $this->fileRepositoryMock;
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function isFileInExcludeFolderReturnsFalseWhenNoExcludeFoldersConfigured(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');

        self::assertFalse($this->subject->isFileInExcludeFolder($fileMock));
    }

    #[Test]
    public function isFileInExcludeFolderReturnsTrueWhenIdentifierMatchesSingleExcludeFolder(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/excluded/image.jpg');

        self::assertTrue($this->subject->isFileInExcludeFolder($fileMock));
    }

    #[Test]
    public function isFileInExcludeFolderReturnsFalseWhenIdentifierDoesNotMatchAnyExcludeFolder(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/', '/other/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');

        self::assertFalse($this->subject->isFileInExcludeFolder($fileMock));
    }

    #[Test]
    public function isFileInExcludeFolderReturnsTrueWhenIdentifierMatchesSecondOfMultipleExcludeFolders(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/first/', '/second/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/second/image.jpg');

        self::assertTrue($this->subject->isFileInExcludeFolder($fileMock));
    }

    #[Test]
    public function getAbsoluteFilePathJoinsPublicPathAndPublicUrl(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getPublicUrl')->willReturn('fileadmin/user_upload/image.jpg');

        self::assertSame(
            '/var/www/public/fileadmin/user_upload/image.jpg',
            $this->subject->getAbsoluteFilePath($fileMock),
        );
    }

    #[Test]
    public function getAbsoluteFilePathUrlDecodesPublicUrl(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getPublicUrl')->willReturn('fileadmin/user_upload/image%20name.jpg');

        self::assertSame(
            '/var/www/public/fileadmin/user_upload/image name.jpg',
            $this->subject->getAbsoluteFilePath($fileMock),
        );
    }

    #[Test]
    public function markFileAsCompressedUpdatesCompressionStatusWithCompressInfo(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(42);

        $this->fileRepositoryMock
            ->expects(self::once())
            ->method('updateCompressionStatus')
            ->with(42, true, '', 'tinify: 1 KB -> 512 B (-50%) - 01.01.2026');

        $this->subject->markFileAsCompressed($fileMock, 'tinify: 1 KB -> 512 B (-50%) - 01.01.2026');
    }

    #[Test]
    public function markFileAsCompressedDefaultsCompressInfoToEmptyString(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(7);

        $this->fileRepositoryMock
            ->expects(self::once())
            ->method('updateCompressionStatus')
            ->with(7, true, '', '');

        $this->subject->markFileAsCompressed($fileMock);
    }

    #[Test]
    public function buildCompressInfoWithoutToolOmitsToolSegment(): void
    {
        $date = date('d.m.Y');

        self::assertSame(
            sprintf('tinify: 1 KB -> 512 B (-50%%) - %s', $date),
            $this->subject->buildCompressInfo('tinify', 1024, 512),
        );
    }

    #[Test]
    public function buildCompressInfoWithToolIncludesToolSegment(): void
    {
        $date = date('d.m.Y');

        self::assertSame(
            sprintf('local-tools (jpegoptim): 1 KB -> 512 B (-50%%) - %s', $date),
            $this->subject->buildCompressInfo('local-tools', 1024, 512, 'jpegoptim'),
        );
    }

    #[Test]
    public function buildCompressInfoWithEmptyStringToolOmitsToolSegment(): void
    {
        $date = date('d.m.Y');

        self::assertSame(
            sprintf('tinify: 1 KB -> 512 B (-50%%) - %s', $date),
            $this->subject->buildCompressInfo('tinify', 1024, 512, ''),
        );
    }

    #[Test]
    public function formatFileSizeFormatsBytes(): void
    {
        self::assertSame('500 B', $this->subject->formatFileSize(500));
    }

    #[Test]
    public function formatFileSizeFormatsZeroBytes(): void
    {
        self::assertSame('0 B', $this->subject->formatFileSize(0));
    }

    #[Test]
    public function formatFileSizeFormatsJustBelowKilobyteBoundaryAsBytes(): void
    {
        self::assertSame('1023 B', $this->subject->formatFileSize(1023));
    }

    #[Test]
    public function formatFileSizeFormatsExactlyOneKilobyteBoundary(): void
    {
        self::assertSame('1 KB', $this->subject->formatFileSize(1024));
    }

    #[Test]
    public function formatFileSizeFormatsKilobytes(): void
    {
        self::assertSame('10 KB', $this->subject->formatFileSize(10240));
    }

    #[Test]
    public function formatFileSizeFormatsJustBelowMegabyteBoundaryAsKilobytes(): void
    {
        self::assertSame('1024 KB', $this->subject->formatFileSize(1048575));
    }

    #[Test]
    public function formatFileSizeFormatsExactlyOneMegabyteBoundary(): void
    {
        self::assertSame('1.0 MB', $this->subject->formatFileSize(1048576));
    }

    #[Test]
    public function formatFileSizeFormatsMegabytes(): void
    {
        self::assertSame('2.5 MB', $this->subject->formatFileSize((int) (2.5 * 1048576)));
    }

    #[Test]
    public function calculateSavedPercentComputesPercentageSaved(): void
    {
        self::assertSame(50, $this->subject->calculateSavedPercent(1000, 500));
    }

    #[Test]
    public function calculateSavedPercentReturnsZeroWhenOriginalSizeIsZero(): void
    {
        self::assertSame(0, $this->subject->calculateSavedPercent(0, 500));
    }

    #[Test]
    public function calculateSavedPercentReturnsZeroWhenNewSizeIsZero(): void
    {
        self::assertSame(0, $this->subject->calculateSavedPercent(1000, 0));
    }

    #[Test]
    public function calculateSavedPercentReturnsZeroWhenOriginalSizeIsNegative(): void
    {
        self::assertSame(0, $this->subject->calculateSavedPercent(-100, 500));
    }

    #[Test]
    public function calculateSavedPercentReturnsZeroWhenNewSizeIsNegative(): void
    {
        self::assertSame(0, $this->subject->calculateSavedPercent(1000, -1));
    }

    #[Test]
    public function calculateSavedPercentReturnsNegativeValueWhenFileGrew(): void
    {
        // File grew instead of shrinking: the trait does not clamp to zero here,
        // callers only branch on ">0" when deciding whether to report success.
        self::assertSame(-100, $this->subject->calculateSavedPercent(500, 1000));
    }

    #[Test]
    public function buildStoragePathJoinsPublicPathBaseAndIdentifier(): void
    {
        self::assertSame(
            '/var/www/public/fileadmin/_processed_/8/f/csm_img.png',
            $this->subject->buildStoragePath('/var/www/public/', 'fileadmin', '/_processed_/8/f/csm_img.png'),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalIdentifierDataProvider(): array
    {
        return [
            'parent traversal' => ['/_processed_/../../typo3conf/system/settings.php'],
            'nested traversal' => ['/user_upload/../../../etc/passwd'],
            'backslash traversal' => ['\\..\\..\\windows'],
            'empty identifier' => [''],
        ];
    }

    #[Test]
    #[DataProvider('traversalIdentifierDataProvider')]
    public function buildStoragePathReturnsNullForUnsafeIdentifier(string $identifier): void
    {
        self::assertNull(
            $this->subject->buildStoragePath('/var/www/public/', 'fileadmin/', $identifier),
        );
    }

    #[Test]
    public function resolveProcessedFilePathUsesStorageBasePathConfiguration(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => 'fileadmin/']);

        self::assertSame(
            '/var/www/public/fileadmin/_processed_/8/f/csm_img.png',
            $this->subject->resolveProcessedFilePath($storageMock, '_processed_/8/f/csm_img.png'),
        );
    }

    #[Test]
    public function resolveProcessedFilePathReturnsNullForTraversalIdentifier(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => 'fileadmin/']);

        self::assertNull(
            $this->subject->resolveProcessedFilePath($storageMock, '../../etc/passwd'),
        );
    }

    #[Test]
    public function resolveProcessedFilePathDefaultsToEmptyBasePathWhenMissing(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn([]);

        self::assertSame(
            '/var/www/public/_processed_/8/f/csm_img.png',
            $this->subject->resolveProcessedFilePath($storageMock, '_processed_/8/f/csm_img.png'),
        );
    }

    #[Test]
    public function updateFileInformationUpdatesIndexEntryViaIndexer(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getStorage')->willReturn($storageMock);

        $indexerMock = $this->createMock(Indexer::class);
        $indexerMock->expects(self::once())->method('updateIndexEntry')->with($fileMock);

        GeneralUtility::addInstance(Indexer::class, $indexerMock);

        $this->subject->updateFileInformation($fileMock);
    }
}
