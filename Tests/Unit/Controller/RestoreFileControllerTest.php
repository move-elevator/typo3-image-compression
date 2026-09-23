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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\FormProtection\{BackendFormProtection, FormProtectionFactory};
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\{FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\{File, ResourceFactory};

/**
 * RestoreFileControllerTest.
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
    private ResourceFactory&MockObject $resourceFactoryMock;
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
        $this->resourceFactoryMock = $this->createMock(ResourceFactory::class);
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
            $this->resourceFactoryMock,
            $this->formProtectionFactoryMock,
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['LANG'] = $this->originalLang;
    }

    #[Test]
    public function mainActionRestoresWhenTokenIsValidAndFileMayBeReplaced(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('checkActionPermission')->with('replace')->willReturn(true);
        $this->resourceFactoryMock->method('getFileObject')->with(5)->willReturn($fileMock);
        $this->formProtectionMock->method('validateToken')
            ->with('valid-token', RestoreFileController::FORM_PROTECTION_FORM_NAME, RestoreFileController::FORM_PROTECTION_ACTION, '5')
            ->willReturn(true);

        $this->restoreServiceMock->expects(self::once())->method('restoreByUid')->with(5)->willReturn(true);

        $this->subject->mainAction($this->createRequest(5, 'valid-token'));
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenTokenIsInvalid(): void
    {
        $this->formProtectionMock->method('validateToken')->willReturn(false);
        $this->resourceFactoryMock->expects(self::never())->method('getFileObject');
        $this->restoreServiceMock->expects(self::never())->method('restoreByUid');

        $this->subject->mainAction($this->createRequest(5, 'forged-token'));
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenCurrentUserMayNotReplaceTheFile(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('checkActionPermission')->with('replace')->willReturn(false);
        $this->resourceFactoryMock->method('getFileObject')->with(5)->willReturn($fileMock);
        $this->formProtectionMock->method('validateToken')->willReturn(true);

        $this->restoreServiceMock->expects(self::never())->method('restoreByUid');

        $this->subject->mainAction($this->createRequest(5, 'valid-token'));
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenTheFileDoesNotExist(): void
    {
        $this->resourceFactoryMock->method('getFileObject')->with(5)->willThrowException(new FileDoesNotExistException());
        $this->formProtectionMock->method('validateToken')->willReturn(true);

        $this->restoreServiceMock->expects(self::never())->method('restoreByUid');

        $this->subject->mainAction($this->createRequest(5, 'valid-token'));
    }

    private function createRequest(int $fileUid, string $formToken): ServerRequestInterface
    {
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(['fileUid' => (string) $fileUid, 'formToken' => $formToken]);

        return $requestMock;
    }
}
