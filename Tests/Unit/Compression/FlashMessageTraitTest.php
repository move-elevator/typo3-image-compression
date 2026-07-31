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

use MoveElevator\Typo3ImageCompression\Compression\FlashMessageTrait;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Localization\{LanguageService, LanguageServiceFactory, Locale};
use TYPO3\CMS\Core\Messaging\{FlashMessage, FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FlashMessageTraitTestSubject.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface FlashMessageTraitTestSubject
{
    /**
     * @param array<int, string> $replaceMarkers
     */
    public function callAddFlashMessage(
        string $key,
        array $replaceMarkers = [],
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR,
    ): void;
}

/**
 * FlashMessageTraitTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(FlashMessageTrait::class)]
final class FlashMessageTraitTest extends TestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function addFlashMessageReturnsEarlyInCliContext(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            false,
            '/tmp',
            '/tmp/public',
            '/tmp/var',
            '/tmp/config',
            'phpunit',
            'UNIX',
        );

        $subject = $this->createSubject();

        // No FlashMessage/FlashMessageService instance is queued. If the method
        // did not return early, it would attempt to resolve LocalizationUtility
        // and FlashMessageService for real, which would fail without a full
        // TYPO3 boot. A clean, exception-free call proves the early return.
        $this->expectNotToPerformAssertions();

        $subject->callAddFlashMessage('someKey');
    }

    #[Test]
    public function addFlashMessageEnqueuesTranslatedMessageInNonCliContext(): void
    {
        Environment::initialize(
            new ApplicationContext('Testing'),
            false,
            false,
            '/tmp',
            '/tmp/public',
            '/tmp/var',
            '/tmp/config',
            'phpunit',
            'UNIX',
        );

        // TYPO3 is not booted, so LocalizationUtility::translate() cannot resolve
        // its real dependency chain (CacheManager -> "runtime" cache and the
        // LanguageServiceFactory it needs to build a LanguageService). Both are
        // faked here so translate() can run to completion without a full boot.
        $runtimeCacheMock = $this->createMock(FrontendInterface::class);
        /** @var array<string, mixed> $runtimeCacheStorage */
        $runtimeCacheStorage = [];
        $runtimeCacheMock->method('get')->willReturnCallback(
            static function (string $id) use (&$runtimeCacheStorage): mixed {
                return $runtimeCacheStorage[$id] ?? false;
            },
        );
        $runtimeCacheMock->method('set')->willReturnCallback(
            static function (string $id, mixed $data) use (&$runtimeCacheStorage): void {
                $runtimeCacheStorage[$id] = $data;
            },
        );

        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->with('runtime')->willReturn($runtimeCacheMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('getLocale')->willReturn(new Locale('en'));
        // TYPO3 v12/v13's LocalizationUtility::translate() resolves labels via
        // LanguageService::sL(), while v14 calls LanguageService::translate()
        // directly instead (a method that doesn't exist at all on v12/v13, so
        // it can only be stubbed when present). Covering both handles every
        // supported version.
        $languageServiceMock->method('sL')->willReturn('translated label');
        if (method_exists(LanguageService::class, 'translate')) {
            $languageServiceMock->method('translate')->willReturn('translated label');
        }

        $languageServiceFactoryMock = $this->createMock(LanguageServiceFactory::class);
        $languageServiceFactoryMock->method('createFromUserPreferences')->willReturn($languageServiceMock);
        $languageServiceFactoryMock->method('create')->willReturn($languageServiceMock);

        // GeneralUtility::makeInstance(LanguageServiceFactory::class) is invoked
        // multiple times across the two translate() calls (message + title); queue
        // enough instances to satisfy every invocation.
        for ($i = 0; $i < 6; ++$i) {
            GeneralUtility::addInstance(LanguageServiceFactory::class, $languageServiceFactoryMock);
        }

        $queueMock = $this->createMock(FlashMessageQueue::class);
        $queueMock->expects(self::once())->method('enqueue')->with(self::isInstanceOf(FlashMessage::class));

        $flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $flashMessageServiceMock->method('getMessageQueueByIdentifier')->willReturn($queueMock);
        GeneralUtility::setSingletonInstance(FlashMessageService::class, $flashMessageServiceMock);

        $subject = $this->createSubject();
        $subject->callAddFlashMessage('someKey');
    }

    private function createSubject(): FlashMessageTraitTestSubject
    {
        return new class implements FlashMessageTraitTestSubject {
            use FlashMessageTrait;

            public function callAddFlashMessage(
                string $key,
                array $replaceMarkers = [],
                ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR,
            ): void {
                $this->addFlashMessage($key, $replaceMarkers, $severity);
            }
        };
    }
}
