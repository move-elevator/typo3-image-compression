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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Backend;

use MoveElevator\Typo3ImageCompression\Backend\RestoreButton;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;
use TYPO3\CMS\Core\Imaging\Icon;

/**
 * RestoreButtonTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
#[CoversClass(RestoreButton::class)]
final class RestoreButtonTest extends TestCase
{
    #[Test]
    public function implementsButtonInterface(): void
    {
        self::assertInstanceOf(ButtonInterface::class, $this->createSubject());
    }

    #[Test]
    public function isValidReturnsTrueForPositiveFileUidAndNonEmptyLabel(): void
    {
        self::assertTrue($this->createSubject()->isValid());
    }

    #[Test]
    public function isValidReturnsFalseForZeroFileUid(): void
    {
        self::assertFalse($this->createSubject('/typo3-image-compression/restore', 0)->isValid());
    }

    #[Test]
    public function isValidReturnsFalseForEmptyLabel(): void
    {
        self::assertFalse($this->createSubject('/typo3-image-compression/restore', 5, '')->isValid());
    }

    #[Test]
    public function renderProducesAPlainButtonCarryingTheActionUrlAndFileUidAsDataAttributes(): void
    {
        $html = $this->createSubject('/typo3-image-compression/restore', 42)->render();

        // Not a <form>: the button is rendered inside EXT:filelist's own
        // page-level <form name="fileListForm">, and nested <form> elements
        // are invalid HTML. Browsers silently drop the inner <form> tag and
        // reassociate its inputs with the outer form instead, so a real
        // <form> here submits fileListForm's own search action and never
        // reaches RestoreFileController. A plain button driven by JS avoids
        // the nesting entirely.
        self::assertStringNotContainsString('<form', $html);
        self::assertStringContainsString('type="button"', $html);
        self::assertStringContainsString('data-restorefileaction-url="/typo3-image-compression/restore"', $html);
        self::assertStringContainsString('data-restorefileaction-uid="42"', $html);
    }

    #[Test]
    public function renderProducesTheFormTokenAsADataAttribute(): void
    {
        $html = $this->createSubject('/typo3-image-compression/restore', 42, 'Restore original file', 'the-token')->render();

        self::assertStringContainsString('data-restorefileaction-token="the-token"', $html);
    }

    #[Test]
    public function renderEscapesTheFormTokenAttributeValue(): void
    {
        $html = $this->createSubject('/typo3-image-compression/restore', 42, 'Restore original file', '"><script>')->render();

        self::assertStringNotContainsString('"><script>', $html);
    }

    #[Test]
    public function toStringMatchesRender(): void
    {
        $subject = $this->createSubject();

        self::assertSame($subject->render(), (string) $subject);
    }

    private function createSubject(
        string $actionUrl = '/typo3-image-compression/restore',
        int $fileUid = 5,
        string $label = 'Restore original file',
        string $formToken = 'token',
    ): RestoreButton {
        $iconMock = $this->createMock(Icon::class);
        $iconMock->method('render')->willReturn('<span class="icon"></span>');

        return new RestoreButton($actionUrl, $fileUid, $label, $iconMock, $formToken);
    }
}
