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

namespace MoveElevator\Typo3ImageCompression\Tests\Functional\Form\Element;

use MoveElevator\Typo3ImageCompression\Form\Element\CompressionInfoElement;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * CompressionInfoElementTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(CompressionInfoElement::class)]
final class CompressionInfoElementTest extends FunctionalTestCase
{
    /**
     * Full baseline extension configuration mirroring ext_conf_template.txt defaults.
     *
     * TYPO3's core ExtensionConfiguration::set() persists the given configuration to
     * LocalConfiguration.php on disk, which survives across test methods of the same
     * functional test case (only the database is truncated between tests). Re-applying
     * this known-good baseline in setUp() prevents a partial override from a previous
     * test method leaking into the next one.
     *
     * @var array<string, mixed>
     */
    private const DEFAULT_EXTENSION_CONFIGURATION = [
        'provider' => 'tinify',
        'apiKey' => '',
        'debug' => false,
        'excludeFolders' => '',
        'mimeTypes' => 'image/avif,image/jpeg,image/png,image/webp',
        'jpegQuality' => 85,
        'pngQuality' => 85,
        'webpQuality' => 80,
        'systemInformationToolbar' => true,
        'showCompressionStatus' => true,
        'showStatusReport' => true,
    ];
    protected array $coreExtensionsToLoad = ['reports'];
    protected array $testExtensionsToLoad = ['move-elevator/typo3-image-compression'];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create('default');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_file.csv');
        $this->get(ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            self::DEFAULT_EXTENSION_CONFIGURATION,
        );
    }

    #[Test]
    public function renderReturnsEmptyResultWhenCompressionStatusIsDisabled(): void
    {
        $this->get(ExtensionConfiguration::class)->set(
            'typo3_image_compression',
            [...self::DEFAULT_EXTENSION_CONFIGURATION, 'showCompressionStatus' => false],
        );

        $subject = $this->createSubject(['databaseRow' => ['file' => [2]]]);
        $result = $subject->render();

        self::assertSame('', $result['html']);
    }

    #[Test]
    public function renderReturnsNoFileTemplateWhenFileUidIsZero(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => [0]]]);
        $result = $subject->render();

        self::assertStringContainsString('No file associated', $result['html']);
    }

    #[Test]
    public function renderReturnsNoFileTemplateWhenFileFieldIsEmpty(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => []]]);
        $result = $subject->render();

        self::assertStringContainsString('No file associated', $result['html']);
    }

    #[Test]
    public function renderReturnsFileNotFoundTemplateWhenFileDoesNotExist(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => [999]]]);
        $result = $subject->render();

        self::assertStringContainsString('File not found', $result['html']);
    }

    #[Test]
    public function renderReturnsErrorTemplateWhenFileHasCompressionError(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => [1]]]);
        $result = $subject->render();

        self::assertStringContainsString('Error', $result['html']);
        self::assertStringContainsString('tinify error: 429 Too Many Requests', $result['html']);
    }

    #[Test]
    public function renderReturnsCompressedTemplateWhenFileIsCompressed(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => [2]]]);
        $result = $subject->render();

        self::assertStringContainsString('Compressed', $result['html']);
        self::assertStringContainsString('provider=tinify;original=2000;compressed=1500', $result['html']);
    }

    #[Test]
    public function renderReturnsNotCompressedTemplateWhenFileIsNeitherCompressedNorErrored(): void
    {
        $subject = $this->createSubject(['databaseRow' => ['file' => [3]]]);
        $result = $subject->render();

        self::assertStringContainsString('Not compressed', $result['html']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createSubject(array $data): CompressionInfoElement
    {
        $nodeFactoryMock = $this->createMock(NodeFactory::class);

        return GeneralUtility::makeInstance(CompressionInfoElement::class, $nodeFactoryMock, $data);
    }
}
