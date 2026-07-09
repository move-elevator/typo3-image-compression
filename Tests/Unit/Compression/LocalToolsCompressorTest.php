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

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, LocalToolsCompressor, ToolDetection};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * LocalToolsCompressorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(LocalToolsCompressor::class)]
final class LocalToolsCompressorTest extends TestCase
{
    private LocalToolsCompressor $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;
    private ToolDetection&MockObject $toolDetectionMock;

    /**
     * @var string[]
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            sys_get_temp_dir().'/index.php',
            'UNIX',
        );

        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);
        $this->toolDetectionMock = $this->createMock(ToolDetection::class);

        $this->subject = new LocalToolsCompressor(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
            $this->toolDetectionMock,
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
        $this->tmpFiles = [];

        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function implementsCompressorInterface(): void
    {
        self::assertInstanceOf(CompressorInterface::class, $this->subject);
    }

    #[Test]
    public function getProviderIdentifierReturnsLocalTools(): void
    {
        self::assertSame('local-tools', $this->subject->getProviderIdentifier());
    }

    #[Test]
    public function canSetLogger(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject->setLogger($loggerMock);

        // Verify logger was set by ensuring no exception was thrown
        self::assertInstanceOf(LocalToolsCompressor::class, $this->subject);
    }

    #[Test]
    public function executeOptimizationReturnsFalseWhenToolPathMissing(): void
    {
        $this->toolDetectionMock
            ->method('getToolPath')
            ->with('gifsicle')
            ->willReturn(null);

        self::assertFalse($this->invokeExecuteOptimization('gifsicle', '/tmp/example.gif'));
    }

    #[Test]
    public function executeOptimizationReturnsFalseWhenToolExitsNonZero(): void
    {
        // /usr/bin/false exits with code 1: a non-zero exit must be reported as
        // failure instead of being silently treated as a successful compression.
        $this->toolDetectionMock
            ->method('getToolPath')
            ->with('gifsicle')
            ->willReturn('/usr/bin/false');

        self::assertFalse($this->invokeExecuteOptimization('gifsicle', '/tmp/example.gif'));
    }

    #[Test]
    public function executeOptimizationReturnsTrueWhenToolExitsZero(): void
    {
        $this->toolDetectionMock
            ->method('getToolPath')
            ->with('gifsicle')
            ->willReturn('/usr/bin/true');

        self::assertTrue($this->invokeExecuteOptimization('gifsicle', '/tmp/example.gif'));
    }

    #[Test]
    public function buildStoragePathJoinsPublicPathBaseAndIdentifier(): void
    {
        self::assertSame(
            '/var/www/public/fileadmin/_processed_/8/f/csm_img.png',
            $this->invokeBuildStoragePath('/var/www/public/', 'fileadmin', '/_processed_/8/f/csm_img.png'),
        );
    }

    #[Test]
    public function buildStoragePathDoesNotUrlDecodeIdentifier(): void
    {
        // A literal percent sequence must stay literal and must not be decoded
        // into a traversal sequence (../).
        self::assertSame(
            '/var/www/public/fileadmin/user_upload/foo%2e%2e%2fbar.png',
            $this->invokeBuildStoragePath('/var/www/public/', 'fileadmin/', 'user_upload/foo%2e%2e%2fbar.png'),
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
            $this->invokeBuildStoragePath('/var/www/public/', 'fileadmin/', $identifier),
        );
    }

    #[Test]
    public function compressDoesNothingForNonFileInstance(): void
    {
        $fileInterfaceMock = $this->createMock(FileInterface::class);

        $this->extensionConfigurationMock->expects(self::never())->method('getExcludeFolders');
        $this->extensionConfigurationMock->expects(self::never())->method('getMimeTypes');

        $this->subject->compress($fileInterfaceMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenFileIsInExcludedFolder(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/excluded/image.jpg');
        $fileMock->expects(self::never())->method('getMimeType');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenMimeTypeNotConfigured(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn([]);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->expects(self::never())->method('getPublicUrl');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenNoToolAvailableForMimeType(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn(null);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->expects(self::never())->method('getPublicUrl');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenFileDoesNotExistOnDisk(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn('jpegoptim');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/does-not-exist.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn('does-not-exist-'.bin2hex(random_bytes(8)).'.jpg');

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenFileSizeIsZero(): void
    {
        $tmpFile = $this->createTmpFile('');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn('jpegoptim');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/empty.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressMarksFileAsCompressedWhenOptimizationSucceeds(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn('jpegoptim');
        $this->toolDetectionMock->method('getToolPath')->with('jpegoptim')->willReturn('/usr/bin/true');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));
        $fileMock->method('getUid')->willReturn(99);
        $fileMock->method('getStorage')->willReturn($this->createMock(ResourceStorage::class));

        $indexerMock = $this->createMock(Indexer::class);
        $indexerMock->expects(self::once())->method('updateIndexEntry')->with($fileMock);
        GeneralUtility::addInstance(Indexer::class, $indexerMock);

        // markFileAsCompressed() runs unconditionally on a successful
        // optimization, regardless of whether savedPercent ends up > 0
        // (the tool mock does not actually shrink the file).
        $this->fileRepositoryMock->expects(self::once())->method('updateCompressionStatus')->with(99, true);

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressDoesNotMarkFileAsCompressedWhenOptimizationFails(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn('jpegoptim');
        $this->toolDetectionMock->method('getToolPath')->with('jpegoptim')->willReturn(null);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressProcessedFilesReportsFileStorageNotFound(): void
    {
        $this->fileProcessedRepositoryMock->method('findStorageId')->with(1)->willReturn(0);
        $this->fileProcessedRepositoryMock
            ->expects(self::once())
            ->method('updateCompressState')
            ->with(1, 0, 'file storage not found');

        $this->subject->compressProcessedFiles([['uid' => 1, 'identifier' => '/foo.jpg']]);
    }

    #[Test]
    public function compressProcessedFilesReportsFileNotFound(): void
    {
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => 'fileadmin/']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(2)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->fileProcessedRepositoryMock
            ->expects(self::once())
            ->method('updateCompressState')
            ->with(2, 0, 'file not found');

        $this->subject->compressProcessedFiles([['uid' => 2, 'identifier' => '/_processed_/does-not-exist-'.bin2hex(random_bytes(8)).'.jpg']]);
    }

    #[Test]
    public function compressProcessedFilesReportsFilesizeInvalid(): void
    {
        $tmpFile = $this->createTmpFile('');
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(3)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->fileProcessedRepositoryMock
            ->expects(self::once())
            ->method('updateCompressState')
            ->with(3, 0, 'filesize invalid');

        $this->subject->compressProcessedFiles([['uid' => 3, 'identifier' => basename($tmpFile)]]);
    }

    #[Test]
    public function compressProcessedFilesSkipsWhenNoToolAvailableForMimeType(): void
    {
        $tmpFile = $this->createTmpJpegFile();
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(4)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn(null);
        $this->fileProcessedRepositoryMock->expects(self::never())->method('updateCompressState');

        $this->subject->compressProcessedFiles([['uid' => 4, 'identifier' => basename($tmpFile)]]);
    }

    #[Test]
    public function compressProcessedFilesMarksSuccessWhenOptimizationSucceeds(): void
    {
        $tmpFile = $this->createTmpJpegFile();
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(5)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->toolDetectionMock->method('getFirstAvailable')->with(['jpegoptim'])->willReturn('jpegoptim');
        $this->toolDetectionMock->method('getToolPath')->with('jpegoptim')->willReturn('/usr/bin/true');
        $this->fileProcessedRepositoryMock->expects(self::once())->method('updateCompressState')->with(5);

        $this->subject->compressProcessedFiles([['uid' => 5, 'identifier' => basename($tmpFile)]]);
    }

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function mimeTypeToolsDataProvider(): array
    {
        return [
            'jpeg maps to jpegoptim' => ['image/jpeg', ['jpegoptim']],
            'png maps to optipng and pngquant' => ['image/png', ['optipng', 'pngquant']],
            'gif maps to gifsicle' => ['image/gif', ['gifsicle']],
            'webp maps to cwebp' => ['image/webp', ['cwebp']],
            'avif maps to avifenc' => ['image/avif', ['avifenc']],
        ];
    }

    /**
     * @param string[] $tools
     */
    #[Test]
    #[DataProvider('mimeTypeToolsDataProvider')]
    public function getBestToolForMimeTypeQueriesToolDetectionWithMappedTools(string $mimeType, array $tools): void
    {
        $this->toolDetectionMock
            ->expects(self::once())
            ->method('getFirstAvailable')
            ->with($tools)
            ->willReturn($tools[0]);

        self::assertSame($tools[0], $this->invokeGetBestToolForMimeType($mimeType));
    }

    #[Test]
    public function getBestToolForMimeTypeReturnsNullForUnmappedMimeType(): void
    {
        $this->toolDetectionMock
            ->expects(self::once())
            ->method('getFirstAvailable')
            ->with([])
            ->willReturn(null);

        self::assertNull($this->invokeGetBestToolForMimeType('application/pdf'));
    }

    #[Test]
    public function buildCommandBuildsJpegoptimCommand(): void
    {
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);

        self::assertSame(
            "/usr/bin/jpegoptim --strip-all --all-progressive --max=80 '/tmp/example.jpg'",
            $this->invokeBuildCommand('jpegoptim', '/usr/bin/jpegoptim', '/tmp/example.jpg'),
        );
    }

    #[Test]
    public function buildCommandBuildsPngquantCommand(): void
    {
        $this->extensionConfigurationMock->method('getPngQuality')->willReturn(85);

        self::assertSame(
            "/usr/bin/pngquant --force --ext .png --quality 70-85 '/tmp/example.png'",
            $this->invokeBuildCommand('pngquant', '/usr/bin/pngquant', '/tmp/example.png'),
        );
    }

    #[Test]
    public function buildCommandBuildsPngquantCommandClampsQualityRangeAtZero(): void
    {
        $this->extensionConfigurationMock->method('getPngQuality')->willReturn(10);

        self::assertSame(
            "/usr/bin/pngquant --force --ext .png --quality 0-10 '/tmp/example.png'",
            $this->invokeBuildCommand('pngquant', '/usr/bin/pngquant', '/tmp/example.png'),
        );
    }

    #[Test]
    public function buildCommandBuildsCwebpCommand(): void
    {
        $this->extensionConfigurationMock->method('getWebpQuality')->willReturn(75);

        self::assertSame(
            "/usr/bin/cwebp -q 75 '/tmp/example.webp' -o '/tmp/example.webp'",
            $this->invokeBuildCommand('cwebp', '/usr/bin/cwebp', '/tmp/example.webp'),
        );
    }

    #[Test]
    public function buildCommandBuildsAvifencCommand(): void
    {
        $this->extensionConfigurationMock->method('getWebpQuality')->willReturn(60);

        self::assertSame(
            "/usr/bin/avifenc -q 60 '/tmp/example.avif' '/tmp/example.avif'",
            $this->invokeBuildCommand('avifenc', '/usr/bin/avifenc', '/tmp/example.avif'),
        );
    }

    #[Test]
    public function buildCommandBuildsDefaultOptipngCommandFromToolCommandsMap(): void
    {
        self::assertSame(
            "/usr/bin/optipng -o2 -strip all '/tmp/example.png'",
            $this->invokeBuildCommand('optipng', '/usr/bin/optipng', '/tmp/example.png'),
        );
    }

    #[Test]
    public function buildCommandBuildsDefaultGifsicleCommandFromToolCommandsMap(): void
    {
        self::assertSame(
            "/usr/bin/gifsicle --batch -O2 '/tmp/example.gif'",
            $this->invokeBuildCommand('gifsicle', '/usr/bin/gifsicle', '/tmp/example.gif'),
        );
    }

    private function createTmpFile(string $content, string $suffix = '.jpg'): string
    {
        $tmpFile = sys_get_temp_dir().'/ltc_'.bin2hex(random_bytes(8)).$suffix;
        file_put_contents($tmpFile, $content);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    /**
     * Creates a genuine, tiny JPEG file so mime_content_type() reliably
     * detects it as "image/jpeg" (content-based, not extension-based).
     */
    private function createTmpJpegFile(): string
    {
        $tmpFile = sys_get_temp_dir().'/ltc_'.bin2hex(random_bytes(8)).'.jpg';
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tmpFile);
        imagedestroy($image);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    private function invokeExecuteOptimization(string $tool, string $filePath): bool
    {
        $method = new ReflectionMethod($this->subject, 'executeOptimization');

        /** @var bool $result */
        $result = $method->invoke($this->subject, $tool, $filePath);

        return $result;
    }

    private function invokeBuildStoragePath(string $publicPath, string $basePath, string $identifier): ?string
    {
        $method = new ReflectionMethod($this->subject, 'buildStoragePath');

        /** @var string|null $result */
        $result = $method->invoke($this->subject, $publicPath, $basePath, $identifier);

        return $result;
    }

    private function invokeGetBestToolForMimeType(string $mimeType): ?string
    {
        $method = new ReflectionMethod($this->subject, 'getBestToolForMimeType');

        /** @var string|null $result */
        $result = $method->invoke($this->subject, $mimeType);

        return $result;
    }

    private function invokeBuildCommand(string $tool, string $toolPath, string $filePath): string
    {
        $method = new ReflectionMethod($this->subject, 'buildCommand');

        /** @var string $result */
        $result = $method->invoke($this->subject, $tool, $toolPath, $filePath);

        return $result;
    }
}
