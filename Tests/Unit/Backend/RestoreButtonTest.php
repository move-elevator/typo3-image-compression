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
    public function renderProducesAPostFormWithTheFileUidAsHiddenField(): void
    {
        $html = $this->createSubject('/typo3-image-compression/restore', 42)->render();

        self::assertStringContainsString('<form', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('action="/typo3-image-compression/restore"', $html);
        self::assertStringContainsString('name="fileUid" value="42"', $html);
        self::assertStringContainsString('type="submit"', $html);
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
    ): RestoreButton {
        $iconMock = $this->createMock(Icon::class);
        $iconMock->method('render')->willReturn('<span class="icon"></span>');

        return new RestoreButton($actionUrl, $fileUid, $label, $iconMock);
    }
}
