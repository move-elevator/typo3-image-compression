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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Updates;

use MoveElevator\Typo3ImageCompression\Updates\CompressionColumnsUpgradeWizard;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CompressionColumnsUpgradeWizardTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionColumnsUpgradeWizard::class)]
final class CompressionColumnsUpgradeWizardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['reports'];
    protected array $testExtensionsToLoad = ['move-elevator/typo3-image-compression'];

    private CompressionColumnsUpgradeWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $this->subject = $this->get(CompressionColumnsUpgradeWizard::class);
    }

    #[Test]
    public function updateNecessaryReturnsFalseWithoutLegacyRows(): void
    {
        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsTrueForLegacyCompressedRowWithoutProvider(): void
    {
        $this->importLegacyRow();

        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseForAlreadyMigratedRow(): void
    {
        $this->importLegacyRow();
        $this->subject->executeUpdate();

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateResetsColumnsToUnknownAndLeavesCompressedFlagIntact(): void
    {
        $this->importLegacyRow();

        self::assertTrue($this->subject->executeUpdate());

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compressed', 'compress_provider', 'compress_tstamp')
            ->from('sys_file')
            ->where('uid = 1')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame(1, (int) $row['compressed']);
        self::assertSame('unknown', $row['compress_provider']);
        // The actual compression time of a legacy row is not known, so
        // compress_tstamp is left at its column default (0) rather than
        // being fabricated as "now".
        self::assertSame(0, (int) $row['compress_tstamp']);
    }

    #[Test]
    public function executeUpdateDoesNotTouchNonCompressedRows(): void
    {
        $this->importLegacyRow();

        $this->subject->executeUpdate();

        $row = $this->getConnectionPool()
            ->getQueryBuilderForTable('sys_file')
            ->select('compress_provider')
            ->from('sys_file')
            ->where('uid = 2')
            ->executeQuery()
            ->fetchAssociative();

        self::assertNotFalse($row);
        self::assertSame('', $row['compress_provider']);
    }

    #[Test]
    public function getPrerequisitesReturnsDatabaseUpdatedPrerequisite(): void
    {
        self::assertSame(
            [\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class],
            $this->subject->getPrerequisites(),
        );
    }

    private function importLegacyRow(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file');
        $connection->insert('sys_file', [
            'uid' => 1,
            'pid' => 0,
            'storage' => 1,
            'identifier' => '/legacy.jpg',
            'identifier_hash' => sha1('/legacy.jpg'),
            'folder_hash' => sha1('/'),
            'name' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'missing' => 0,
            'compressed' => 1,
        ]);
        $connection->insert('sys_file', [
            'uid' => 2,
            'pid' => 0,
            'storage' => 1,
            'identifier' => '/not-compressed.jpg',
            'identifier_hash' => sha1('/not-compressed.jpg'),
            'folder_hash' => sha1('/'),
            'name' => 'not-compressed.jpg',
            'mime_type' => 'image/jpeg',
            'missing' => 0,
            'compressed' => 0,
        ]);
    }
}
