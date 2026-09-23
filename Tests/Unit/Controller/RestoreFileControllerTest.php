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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Controller;

use MoveElevator\Typo3ImageCompression\Backup\RestoreService;
use MoveElevator\Typo3ImageCompression\Controller\RestoreFileController;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\FormProtection\{BackendFormProtection, FormProtectionFactory};
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\{FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * RestoreFileControllerTest.
 *
 * Every scenario that resolves a file via ResourceFactory is covered by
 * Tests/Functional/Controller/RestoreFileControllerTest.php instead of here:
 * ResourceFactory is declared "readonly" starting with TYPO3 13.4, which
 * PHPUnit's createMock() cannot double on every resolvable PHPUnit version,
 * and its internal caching implementation isn't stable across the supported
 * TYPO3 versions either, so no unit-level double survives the full version
 * matrix. The one scenario below never reaches ResourceFactory at all: an
 * invalid token short-circuits mainAction() before file resolution.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreFileController::class)]
final class RestoreFileControllerTest extends TestCase
{
    private RestoreService&MockObject $restoreServiceMock;
    private UriBuilder&MockObject $uriBuilderMock;
    private FlashMessageService&MockObject $flashMessageServiceMock;
    private ResourceFactory $resourceFactory;
    private FormProtectionFactory&MockObject $formProtectionFactoryMock;
    private BackendFormProtection&MockObject $formProtectionMock;
    private RestoreFileController $subject;
    private mixed $originalLang = null;

    protected function setUp(): void
    {
        $this->restoreServiceMock = $this->createMock(RestoreService::class);
        $this->uriBuilderMock = $this->createMock(UriBuilder::class);
        $this->uriBuilderMock->method('buildUriFromRoute')->willReturn(new Uri('/module/main'));
        $this->flashMessageServiceMock = $this->createMock(FlashMessageService::class);
        $this->flashMessageServiceMock->method('getMessageQueueByIdentifier')->willReturn($this->createMock(FlashMessageQueue::class));
        $this->resourceFactory = (new ReflectionClass(ResourceFactory::class))->newInstanceWithoutConstructor();
        $this->formProtectionFactoryMock = $this->createMock(FormProtectionFactory::class);
        $this->formProtectionMock = $this->createMock(BackendFormProtection::class);
        $this->formProtectionFactoryMock->method('createForType')->with('backend')->willReturn($this->formProtectionMock);

        $this->originalLang = $GLOBALS['LANG'] ?? null;
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturn('label');
        $GLOBALS['LANG'] = $languageServiceMock;

        $this->subject = new RestoreFileController(
            $this->restoreServiceMock,
            $this->uriBuilderMock,
            $this->flashMessageServiceMock,
            $this->resourceFactory,
            $this->formProtectionFactoryMock,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['LANG'] = $this->originalLang;
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenTokenIsInvalid(): void
    {
        $this->formProtectionMock->method('validateToken')->willReturn(false);
        $this->restoreServiceMock->expects(self::never())->method('restoreByUid');

        $this->subject->mainAction($this->createRequest(5, 'forged-token'));
    }

    private function createRequest(int $fileUid, string $formToken): ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(['fileUid' => (string) $fileUid, 'formToken' => $formToken]);

        return $requestMock;
    }
}
