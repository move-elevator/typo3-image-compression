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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Imaging\{Icon, IconFactory};

use function md5;

/**
 * IconFactoryTestDoubleFactory.
 *
 * IconFactory is declared "readonly" on TYPO3 13.4+, which PHPUnit refuses
 * to double directly (ClassIsReadonlyException), and which a hand-rolled
 * subclass cannot work around either: PHP requires a class's readonly-ness
 * to match its parent's exactly, in both directions, so no single static
 * subclass declaration can extend IconFactory correctly across both the
 * v12.4 floor (not readonly, no injected cache) and the v13.4+ ceiling
 * (readonly, cache injected via a FrontendInterface constructor argument).
 *
 * This constructs a *real* IconFactory instead, via its real constructor,
 * with a mock built for each constructor parameter by its declared type
 * (whatever those parameters are on the installed version — reflection
 * reads them at runtime, nothing is hardcoded). Because none of
 * IconFactory's own dependencies are themselves readonly or final, they can
 * all be doubled normally. The given Icon is then pre-seeded into whichever
 * caching mechanism getIcon() checks first, so it returns immediately
 * without ever needing the rest of those dependencies (icon registry
 * lookups, DI container, ...) to behave realistically.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class IconFactoryTestDoubleFactory
{
    /**
     * @param string $identifier the exact icon identifier the code under test requests, with default size/overlay/state
     */
    public static function create(TestCase $testCase, string $identifier, ?Icon $iconToReturn = null): IconFactory
    {
        $iconToReturn ??= new Icon();
        $reflectionClass = new ReflectionClass(IconFactory::class);

        $arguments = [];
        $cacheMock = null;
        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if (null === $typeName) {
                continue;
            }

            /** @var object&\PHPUnit\Framework\MockObject\MockObject $mock */
            $mock = $testCase->getMockBuilder($typeName)->disableOriginalConstructor()->getMock();
            $arguments[$parameter->getPosition()] = $mock;

            if (is_a($typeName, FrontendInterface::class, true)) {
                $cacheMock = $mock;
            }
        }

        $iconFactory = $reflectionClass->newInstanceArgs($arguments);

        if (null !== $cacheMock) {
            // TYPO3 13.4+: IconFactory reads its result cache via an
            // injected FrontendInterface. Any lookup returns our icon.
            $cacheMock->method('get')->willReturn($iconToReturn);
        } else {
            // TYPO3 12.4: no injected cache, IconFactory keeps its own
            // static in-memory result array instead. Pre-seed the exact
            // entry getIcon() will look up, mirroring its cache-key formula
            // (identifier + size + overlayIdentifier + state, all default
            // here since the only production call site passes just an
            // identifier and Icon::SIZE_SMALL).
            $cacheProperty = $reflectionClass->getProperty('iconCache');
            // md5() here isn't a hashing decision of ours: it must reproduce
            // IconFactory::getIcon()'s own v12.4 cache-key algorithm exactly,
            // or the pre-seeded entry is never found.
            $cacheProperty->setValue(null, [
                md5($identifier.Icon::SIZE_SMALL.'') => $iconToReturn,
            ]);
        }

        return $iconFactory;
    }
}
