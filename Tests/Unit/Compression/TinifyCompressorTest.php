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

use MoveElevator\Typo3ImageCompression\Compression\{CompressorInterface, QuotaAwareInterface, TinifyCompressor};
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Domain\Repository\{FileProcessedRepository, FileRepository};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tinify\Tinify;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Resource\{File, FileInterface, ResourceStorage, StorageRepository};
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TinifyCompressorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(TinifyCompressor::class)]
final class TinifyCompressorTest extends TestCase
{
    private TinifyCompressor $subject;
    private FileRepository&MockObject $fileRepositoryMock;
    private FileProcessedRepository&MockObject $fileProcessedRepositoryMock;
    private ExtensionConfiguration&MockObject $extensionConfigurationMock;
    private StorageRepository&MockObject $storageRepositoryMock;
    private FrontendInterface&MockObject $cacheMock;

    /**
     * @var string[]
     */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        // TinifyCompressor's FlashMessageTrait::addFlashMessage() early-returns
        // in CLI context, keeping it a safe no-op without mocking the TYPO3
        // backend flash message queue.
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
        $this->cacheMock = $this->createMock(FrontendInterface::class);

        $this->subject = new TinifyCompressor(
            $this->fileRepositoryMock,
            $this->fileProcessedRepositoryMock,
            $this->extensionConfigurationMock,
            $this->storageRepositoryMock,
            $this->cacheMock,
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

        // Tinify's key/client/compression-count are process-wide statics;
        // setKey(null) also resets the client, so a single call is enough to
        // stop them leaking into other tests.
        Tinify::setKey(null);
        Tinify::setCompressionCount(null);

        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function implementsCompressorInterface(): void
    {
        self::assertInstanceOf(CompressorInterface::class, $this->subject);
    }

    #[Test]
    public function implementsQuotaAwareInterface(): void
    {
        self::assertInstanceOf(QuotaAwareInterface::class, $this->subject);
    }

    #[Test]
    public function getProviderIdentifierReturnsTinify(): void
    {
        self::assertSame('tinify', $this->subject->getProviderIdentifier());
    }

    #[Test]
    public function initActionDoesNothingWhenApiKeyIsEmpty(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getApiKey')
            ->willReturn('');

        // If initAction tries to call Tinify functions, the test would fail
        // because Tinify is not mocked and would throw an error.
        // The expects(self::once()) assertion validates the early return.
        $this->subject->initAction();
    }

    #[Test]
    public function initActionValidatesOnlyOncePerRequest(): void
    {
        // The API key must be read (and validation attempted) only once, even
        // across multiple files in a batch: the second call short-circuits.
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getApiKey')
            ->willReturn('');

        $this->subject->initAction();
        $this->subject->initAction();
    }

    #[Test]
    public function compressDoesNothingForNonFileInstance(): void
    {
        $fileInterfaceMock = $this->createMock(FileInterface::class);

        // A non-File resource must be ignored before any configuration is read.
        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');
        $this->extensionConfigurationMock->expects(self::never())->method('getExcludeFolders');

        $this->subject->compress($fileInterfaceMock);
    }

    #[Test]
    public function getCompressionCountReturnsCachedValueWithoutCallingApi(): void
    {
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(42);

        // A cache hit must short-circuit before any API interaction; the API
        // key is only read on the (uncached) fetch path.
        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');
        $this->cacheMock->expects(self::never())->method('set');

        self::assertSame(42, $this->subject->getCompressionCount());
    }

    #[Test]
    public function getCompressionCountReturnsNullFromCachedNull(): void
    {
        $this->cacheMock
            ->method('get')
            ->with('compression-count')
            ->willReturn(null);

        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        self::assertNull($this->subject->getCompressionCount());
    }

    #[Test]
    public function getCompressionCountResolvesOnlyOncePerRequest(): void
    {
        // The persistent cache must be read at most once; the second call is
        // served from the per-request memoization.
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(123);

        self::assertSame(123, $this->subject->getCompressionCount());
        self::assertSame(123, $this->subject->getCompressionCount());
    }

    #[Test]
    public function getCompressionCountFetchesFromApiOnCacheMiss(): void
    {
        // A cache miss falls through to fetchCompressionCount(), which reads
        // the Tinify SDK's own static counter (populated as a side effect of
        // any successful API request). With no API key configured,
        // initAction() no-ops without touching that counter, so the value set
        // here is exactly what gets read back and persisted to the cache.
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(false);
        $this->cacheMock
            ->expects(self::once())
            ->method('set')
            ->with('compression-count', 77, [], 900);

        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

        Tinify::setCompressionCount(77);

        self::assertSame(77, $this->subject->getCompressionCount());
    }

    #[Test]
    public function getQuotaLimitReusesCachedCompressionCount(): void
    {
        // getQuotaLimit() delegates to getCompressionCount(); with a cache hit
        // no second lookup and no API call must happen.
        $this->cacheMock
            ->expects(self::once())
            ->method('get')
            ->with('compression-count')
            ->willReturn(10);

        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        self::assertSame(500, $this->subject->getQuotaLimit());
    }

    #[Test]
    public function getQuotaLimitReturnsNullForPaidPlanAboveFreeTier(): void
    {
        $this->cacheMock
            ->method('get')
            ->willReturn(750);

        self::assertNull($this->subject->getQuotaLimit());
    }

    #[Test]
    public function compressReturnsEarlyWhenFileIsInExcludedFolder(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/excluded/image.jpg');
        $fileMock->expects(self::never())->method('getMimeType');

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

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

        $this->extensionConfigurationMock->expects(self::never())->method('isDebug');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressAddsFlashMessageAndReturnsEarlyWhenDebugModeEnabled(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('isDebug')->willReturn(true);
        $this->extensionConfigurationMock->expects(self::never())->method('getApiKey');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');

        $this->fileRepositoryMock->expects(self::never())->method('updateCompressionStatus');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressSavesErrorWhenFileDoesNotExistOnDisk(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('isDebug')->willReturn(false);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/does-not-exist.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn('does-not-exist-'.bin2hex(random_bytes(8)).'.jpg');
        $fileMock->method('getUid')->willReturn(11);

        $this->fileRepositoryMock
            ->expects(self::once())
            ->method('updateCompressionStatus')
            ->with(11, false, self::stringContains('File does not exist'), '');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressSavesErrorWhenFileSizeIsZero(): void
    {
        $tmpFile = $this->createTmpFile('');

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('isDebug')->willReturn(false);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/empty.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));
        $fileMock->method('getUid')->willReturn(12);

        $this->fileRepositoryMock
            ->expects(self::once())
            ->method('updateCompressionStatus')
            ->with(12, false, self::stringContains('Filesize is 0'), '');

        $this->subject->compress($fileMock);
    }

    #[Test]
    public function compressSucceedsAndMarksFileAsCompressedUsingFakeTinifyHttpClient(): void
    {
        // The extension's own apiKey config stays empty, so initAction() is a
        // no-op that never touches Tinify's static key/client. That lets us
        // set them directly: Tinify::setClient() is a genuine, public seam in
        // the SDK for swapping out its HTTP transport, so the real
        // shrink-then-download request sequence in compress() can run to
        // completion (and actually rewrite the file) without any network
        // access or a real API key.
        $tmpFile = $this->createTmpFile(str_repeat('original-bytes', 100));

        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn([]);
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);
        $this->extensionConfigurationMock->method('isDebug')->willReturn(false);
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

        $storageMock = $this->createMock(ResourceStorage::class);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/photo.jpg');
        $fileMock->method('getMimeType')->willReturn('image/jpeg');
        $fileMock->method('getPublicUrl')->willReturn(basename($tmpFile));
        $fileMock->method('getUid')->willReturn(99);
        $fileMock->method('getSize')->willReturn((int) filesize($tmpFile));
        $fileMock->method('getStorage')->willReturn($storageMock);

        $indexerMock = $this->createMock(Indexer::class);
        $indexerMock->expects(self::once())->method('updateIndexEntry')->with($fileMock);
        GeneralUtility::addInstance(Indexer::class, $indexerMock);

        Tinify::setKey('fake-key-for-test');
        Tinify::setClient(new class {
            private int $calls = 0;

            public function request(string $method, string $url, mixed $body = null): object
            {
                ++$this->calls;

                // First call is the "/shrink" upload, which responds with a
                // Location header pointing at the compressed result; the
                // second is the download of that result.
                if (1 === $this->calls) {
                    return (object) ['headers' => ['location' => 'https://fake.tinify.test/output/abc'], 'body' => ''];
                }

                return (object) ['headers' => [], 'body' => 'short'];
            }
        });

        $this->fileRepositoryMock
            ->expects(self::once())
            ->method('updateCompressionStatus')
            ->with(99, true, '', self::stringContains('tinify:'));

        $this->subject->compress($fileMock);

        self::assertSame('short', file_get_contents($tmpFile));
    }

    #[Test]
    public function compressProcessedFilesReportsFileStorageNotFound(): void
    {
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

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
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

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
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');

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
        $this->extensionConfigurationMock->method('getApiKey')->willReturn('');
        $this->extensionConfigurationMock->method('getMimeTypes')->willReturn(['image/jpeg']);

        $tmpFile = $this->createTmpFile('plain text content, not an image');
        $storageMock = $this->createMock(ResourceStorage::class);
        $storageMock->method('getConfiguration')->willReturn(['basePath' => '']);

        $this->fileProcessedRepositoryMock->method('findStorageId')->with(4)->willReturn(1);
        $this->storageRepositoryMock->method('getStorageObject')->with(1)->willReturn($storageMock);
        $this->fileProcessedRepositoryMock->expects(self::never())->method('updateCompressState');

        $this->subject->compressProcessedFiles([['uid' => 4, 'identifier' => basename($tmpFile)]]);
    }

    #[Test]
    public function isFileInExcludeFolderOverrideReturnsTrueForMatchingIdentifier(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/excluded/image.jpg');

        self::assertTrue($this->invokeIsFileInExcludeFolder($fileMock));
    }

    #[Test]
    public function isFileInExcludeFolderOverrideReturnsFalseForNonMatchingIdentifier(): void
    {
        $this->extensionConfigurationMock->method('getExcludeFolders')->willReturn(['/excluded/']);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getIdentifier')->willReturn('/user_upload/image.jpg');

        self::assertFalse($this->invokeIsFileInExcludeFolder($fileMock));
    }

    private function createTmpFile(string $content, string $suffix = '.jpg'): string
    {
        $tmpFile = sys_get_temp_dir().'/tinify_'.bin2hex(random_bytes(8)).$suffix;
        file_put_contents($tmpFile, $content);
        $this->tmpFiles[] = $tmpFile;

        return $tmpFile;
    }

    private function invokeIsFileInExcludeFolder(File $file): bool
    {
        $method = new ReflectionMethod($this->subject, 'isFileInExcludeFolder');

        /** @var bool $result */
        $result = $method->invoke($this->subject, $file);

        return $result;
    }
}
