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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Controller;

use MoveElevator\Typo3ImageCompression\Backup\RestoreService;
use MoveElevator\Typo3ImageCompression\Controller\RestoreFileController;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * RestoreFileControllerTest.
 *
 * Covers the "file does not exist" branch of currentUserMayRestore(),
 * which needs a real, DB-backed ResourceFactory/FileIndexRepository: see
 * Tests/Unit/Controller/RestoreFileControllerTest.php for why that branch
 * isn't exercised as a unit test.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreFileController::class)]
final class RestoreFileControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences(null);
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenTheFileDoesNotExist(): void
    {
        $nonExistentFileUid = 999999;
        $formToken = $this->get(FormProtectionFactory::class)->createForType('backend')->generateToken(
            RestoreFileController::FORM_PROTECTION_FORM_NAME,
            RestoreFileController::FORM_PROTECTION_ACTION,
            (string) $nonExistentFileUid,
        );
        $request = (new ServerRequest())->withParsedBody([
            'fileUid' => (string) $nonExistentFileUid,
            'formToken' => $formToken,
        ]);

        $subject = new RestoreFileController(
            $this->get(RestoreService::class),
            $this->get(UriBuilder::class),
            $this->get(FlashMessageService::class),
            $this->get(ResourceFactory::class),
            $this->get(FormProtectionFactory::class),
        );

        // Session-stored flash messages need an authenticated backend user,
        // which this test doesn't set up; the regression this guards against
        // is FileDoesNotExistException escaping mainAction() uncaught, so a
        // clean redirect response (instead of an uncaught-exception failure)
        // is already the meaningful assertion here.
        $response = $subject->mainAction($request);

        self::assertSame(302, $response->getStatusCode());
    }
}
