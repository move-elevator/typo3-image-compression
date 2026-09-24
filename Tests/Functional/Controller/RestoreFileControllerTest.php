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
use MoveElevator\Typo3ImageCompression\Tests\Functional\Support\FileFixtureTrait;
use PHPUnit\Framework\Attributes\{CoversClass, RunClassInSeparateProcess, Test};
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\{Environment, SystemEnvironmentBuilder};
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * RestoreFileControllerTest.
 *
 * A real, DB-backed environment is used throughout instead of doubling
 * ResourceFactory: PHPUnit refuses to double it on TYPO3 13.4+ (it's
 * declared readonly there), and its internal caching implementation isn't
 * stable across the supported TYPO3 versions either, so no unit-level
 * double is durable here. See Tests/Unit/Controller/RestoreFileControllerTest.php
 * for the one scenario (invalid token) that never touches ResourceFactory
 * and stays a unit test.
 *
 * setUpBackendUser() leaves $GLOBALS['BE_USER'] behind for whichever test
 * runs next in the same process; RunClassInSeparateProcess keeps that
 * contained to this class instead of breaking unrelated tests elsewhere
 * in the suite that rely on the default (no backend user) FAL context.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreFileController::class)]
#[RunClassInSeparateProcess]
final class RestoreFileControllerTest extends FunctionalTestCase
{
    use FileFixtureTrait;

    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences(null);

        // TYPO3's real backend routing sets this on every request before
        // dispatching a controller; ResourceStorage's permission-evaluation
        // aspect (StoragePermissionsAspect) only activates for non-admin
        // users when it's present, so without it every storage silently
        // behaves as fully permitted regardless of the current user.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://typo3-testing.local/typo3/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    protected function tearDown(): void
    {
        // setUpBackendUser() (testing-framework) and setUp() above both set
        // globals directly with no built-in cleanup; left behind, they leak
        // into whichever test runs next in the same PHPUnit process and
        // change its FAL permission/request context. #[RunClassInSeparateProcess]
        // is not sufficient by itself: it depends on isolation actually
        // being effective for the running PHP/PHPUnit combination, so this
        // cleanup stays as the reliable half of the fix.
        unset($GLOBALS['BE_USER'], $GLOBALS['TYPO3_REQUEST'], $GLOBALS['LANG']);

        parent::tearDown();
    }

    #[Test]
    public function isResolvableAsTheBackendRouteTargetTypo3ActuallyDispatches(): void
    {
        // TYPO3\CMS\Core\Http\Dispatcher::getCallableFromTarget() resolves a
        // route target via $container->has()/get(), which only recognizes
        // *public* services and otherwise silently falls back to a bare
        // `new $class()` with no constructor arguments. callMainAction()
        // below builds the subject by hand and would never catch a missing
        // `public: true` in Services.yaml, so this asserts the one thing
        // that actually would: makeInstance() reaching the real container
        // the same way the route dispatcher does.
        $instance = GeneralUtility::makeInstance(RestoreFileController::class);

        self::assertInstanceOf(RestoreFileController::class, $instance);
    }

    #[Test]
    public function mainActionRestoresWhenTokenIsValidAndFileMayBeReplaced(): void
    {
        $this->setUpBackendUser($this->importBackendUser(true));

        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $backupRelativePath = $storageUid.'/backup-hash.jpg';
        $this->writeBackupFile($backupRelativePath, 'original-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', $backupRelativePath);

        // The restored bytes aren't a real JPEG, so real metadata extraction
        // (width/height via GraphicsMagick/ImageMagick) would choke on them;
        // RestoreService re-indexes after every restore, so that's stubbed
        // out here the same way other tests double the Indexer.
        $indexerMock = $this->createMock(Indexer::class);
        GeneralUtility::addInstance(Indexer::class, $indexerMock);

        $response = $this->callMainAction($fileUid);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('original-bytes', file_get_contents(Environment::getPublicPath().'/fileadmin/test/photo.jpg'));
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenCurrentUserMayNotReplaceTheFile(): void
    {
        $this->setUpBackendUser($this->importBackendUser(false));

        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'compressed-bytes');
        $backupRelativePath = $storageUid.'/backup-hash.jpg';
        $this->writeBackupFile($backupRelativePath, 'original-bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg', $backupRelativePath);

        $response = $this->callMainAction($fileUid);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('compressed-bytes', file_get_contents(Environment::getPublicPath().'/fileadmin/test/photo.jpg'));
    }

    #[Test]
    public function mainActionDoesNotRestoreWhenTheFileDoesNotExist(): void
    {
        // Session-stored flash messages need an authenticated backend user,
        // which this test doesn't set up; the regression this guards against
        // is FileDoesNotExistException escaping mainAction() uncaught, so a
        // clean redirect response (instead of an uncaught-exception failure)
        // is already the meaningful assertion here.
        $response = $this->callMainAction(999999);

        self::assertSame(302, $response->getStatusCode());
    }

    private function callMainAction(int $fileUid): ResponseInterface
    {
        $formToken = $this->get(FormProtectionFactory::class)->createForType('backend')->generateToken(
            RestoreFileController::FORM_PROTECTION_FORM_NAME,
            RestoreFileController::FORM_PROTECTION_ACTION,
            (string) $fileUid,
        );
        $request = (new ServerRequest())->withParsedBody([
            'fileUid' => (string) $fileUid,
            'formToken' => $formToken,
        ]);

        $subject = new RestoreFileController(
            $this->get(RestoreService::class),
            $this->get(UriBuilder::class),
            $this->get(FlashMessageService::class),
            $this->get(ResourceFactory::class),
            $this->get(FormProtectionFactory::class),
        );

        return $subject->mainAction($request);
    }
}
