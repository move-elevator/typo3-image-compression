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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Resource;

use MoveElevator\Typo3ImageCompression\Resource\Typo3FileResolver;
use MoveElevator\Typo3ImageCompression\Tests\Functional\Support\FileFixtureTrait;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Resource\File;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Typo3FileResolverTest.
 *
 * findFileByCombinedIdentifier() is exercised against a real ResourceFactory
 * here instead of a unit-level double: ResourceFactory is declared readonly
 * from TYPO3 13.4 onwards, which PHPUnit's createMock() cannot double on
 * every resolvable PHPUnit version (see the same rationale in
 * Tests/Functional/Controller/RestoreFileControllerTest.php).
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(Typo3FileResolver::class)]
final class Typo3FileResolverTest extends FunctionalTestCase
{
    use FileFixtureTrait;

    protected array $testExtensionsToLoad = ['typo3/cms-reports', 'move-elevator/typo3-image-compression'];

    private Typo3FileResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(Typo3FileResolver::class);
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsTheFileForAFileIdentifier(): void
    {
        $storageUid = $this->createLocalTestStorage();
        $this->writeRealFile('photo.jpg', 'bytes');
        $fileUid = $this->importSysFileRow($storageUid, '/photo.jpg', 'photo.jpg');

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/photo.jpg');

        self::assertInstanceOf(File::class, $result);
        self::assertSame($fileUid, $result->getUid());
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsNullForAFolderIdentifier(): void
    {
        $storageUid = $this->createLocalTestStorage();

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/');

        self::assertNull($result);
    }

    #[Test]
    public function findFileByCombinedIdentifierReturnsNullForAnIdentifierThatDoesNotResolve(): void
    {
        $storageUid = $this->createLocalTestStorage();

        $result = $this->subject->findFileByCombinedIdentifier($storageUid.':/nowhere.jpg');

        self::assertNull($result);
    }
}
