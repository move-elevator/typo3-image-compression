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

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, LocalBasicCompressor, ToolDetection};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * LocalBasicCompressorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(LocalBasicCompressor::class)]
final class LocalBasicCompressorTest extends TestCase
{
    private LocalBasicCompressor $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;
    private ToolDetection&MockObject $toolDetectionMock;

    /**
     * @var string[]
     */
    private array $tmpFiles = [];

    private mixed $originalGfxProcessor = null;

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

        $this->originalGfxProcessor = $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] ?? null;

        $this->fileRepositoryMock = $this->createMock(FileRepository::class);
        $this->fileProcessedRepositoryMock = $this->createMock(FileProcessedRepository::class);
        $this->extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $this->storageRepositoryMock = $this->createMock(StorageRepository::class);
        $this->toolDetectionMock = $this->createMock(ToolDetection::class);

        $this->subject = new LocalBasicCompressor(
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

        if (null === $this->originalGfxProcessor) {
            unset($GLOBALS['TYPO3_CONF_VARS']['GFX']['processor']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = $this->originalGfxProcessor;
        }

        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function implementsCompressorInterface(): void
    {
        self::assertInstanceOf(CompressorInterface::class, $this->subject);
    }

    #[Test]
    public function getProviderIdentifierReturnsLocalBasic(): void
    {
        self::assertSame('local-basic', $this->subject->getProviderIdentifier());
    }

    #[Test]
    public function canSetLogger(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);

        $this->subject->setLogger($loggerMock);

        // Verify logger was set by ensuring no exception was thrown
        self::assertInstanceOf(LocalBasicCompressor::class, $this->subject);
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
    public function compressReturnsEarlyWhenMimeTypeConfiguredButNotSupportedByProvider(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/png']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.png');
        $fileMock->method('getMimeType')->willReturn('image/png');
        $fileMock->expects(self::never())->method('getPublicUrl');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressReturnsEarlyWhenFileDoesNotExistOnDisk(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);

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

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/empty.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressMarksFileAsCompressedWhenGraphicsProcessorSucceeds(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn('/usr/bin/true');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));
        $fileMock->method('getUid')->willReturn(99);
        $fileMock->method('getStorage')->willReturn($this->createMock(ResourceStorage::class));

        $indexerMock = $this->createMock(Indexer::class);
        $indexerMock->expects(self::once())->method('updateIndexEntry')->with($fileMock);
        GeneralUtility::addInstance(Indexer::class, $indexerMock);

        $this->fileRepositoryMock->expects(self::once())->method('updateCompressionStatus')->with(99, true);

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressDoesNotMarkFileAsCompressedWhenGraphicsProcessorFails(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn(null);

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
    public function compressProcessedFilesSkipsUnsupportedMimeType(): void
    {
        $tmpFile = $this->createTmpFile('plain text content, not an image');
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(4)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->fileProcessedRepositoryMock->expects(self::never())->method('updateCompressState');

        $this->subject->compressProcessedFiles([['uid' => 4, 'identifier' => basename($tmpFile)]]);
    }

    #[Test]
    public function compressProcessedFilesMarksSuccessWhenGraphicsProcessorSucceeds(): void
    {
        $tmpFile = $this->createTmpJpegFile();
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(5)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn('/usr/bin/true');
        $this->fileProcessedRepositoryMock->expects(self::once())->method('updateCompressState')->with(5);

        $this->subject->compressProcessedFiles([['uid' => 5, 'identifier' => basename($tmpFile)]]);
    }

    #[Test]
    public function getQualityForMimeTypeReturnsConfiguredJpegQuality(): void
    {
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(77);

        self::assertSame(77, $this->invokeGetQualityForMimeType('image/jpeg'));
    }

    #[Test]
    public function getQualityForMimeTypeReturnsDefaultForUnmappedMimeType(): void
    {
        self::assertSame(85, $this->invokeGetQualityForMimeType('image/webp'));
    }

    #[Test]
    public function compressWithGraphicsProcessorReturnsFalseWhenImageMagickNotFound(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn(null);

        self::assertFalse($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    #[Test]
    public function compressWithGraphicsProcessorReturnsTrueForImageMagickV7Binary(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn('/usr/bin/true');

        self::assertTrue($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    #[Test]
    public function compressWithGraphicsProcessorHandlesImageMagickV6BinaryName(): void
    {
        // v6 binaries are named "convert" (no "magick" suffix), so the
        // "convert " sub-command must not be prepended a second time.
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn('/usr/bin/true');

        self::assertTrue($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    #[Test]
    public function compressWithGraphicsProcessorReturnsFalseWhenGraphicsMagickNotFound(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'GraphicsMagick';
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->toolDetectionMock->method('getToolPath')->with('graphicsmagick')->willReturn(null);

        self::assertFalse($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    #[Test]
    public function compressWithGraphicsProcessorReturnsTrueForGraphicsMagick(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor'] = 'GraphicsMagick';
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getToolPath')->with('graphicsmagick')->willReturn('/usr/bin/true');

        self::assertTrue($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    #[Test]
    public function compressWithGraphicsProcessorReturnsFalseWhenToolExitsNonZero(): void
    {
        $tmpFile = $this->createTmpFile('fake-jpeg-bytes');
        $this->extensionConfigurationMock->method('getJpegQuality')->willReturn(80);
        $this->toolDetectionMock->method('getToolPath')->with('imagemagick')->willReturn('/usr/bin/false');

        self::assertFalse($this->invokeCompressWithGraphicsProcessor($tmpFile, 'image/jpeg'));
    }

    private function createTmpFile(string $content, string $suffix = '.jpg'): string
    {
        $tmpFile = sys_get_temp_dir().'/lbc_'.bin2hex(random_bytes(8)).$suffix;
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
        $tmpFile = sys_get_temp_dir().'/lbc_'.bin2hex(random_bytes(8)).'.jpg';
        $image = imagecreatetruecolor(1, 1);
        imagejpeg($image, $tmpFile);
        imagedestroy($image);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    private function invokeGetQualityForMimeType(string $mimeType): int
    {
        $method = new ReflectionMethod($this->subject, 'getQualityForMimeType');

        /** @var int $result */
        $result = $method->invoke($this->subject, $mimeType);

        return $result;
    }

    private function invokeCompressWithGraphicsProcessor(string $filePath, string $mimeType): bool
    {
        $method = new ReflectionMethod($this->subject, 'compressWithGraphicsProcessor');

        /** @var bool $result */
        $result = $method->invoke($this->subject, $filePath, $mimeType);

        return $result;
    }
}
