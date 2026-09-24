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

use Psr\Container\{ContainerInterface, NotFoundExceptionInterface};
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Imaging\{Icon, IconFactory, IconRegistry};

use function is_a;
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
 * PHPUnit's own createMock()/getMockBuilder() are also unusable here beyond
 * that: getMockBuilder() is protected on some resolved PHPUnit versions and
 * cannot be called from outside a TestCase subclass at all.
 *
 * This constructs a *real* IconFactory instead, via its real constructor,
 * with a minimal hand-written stand-in for each constructor parameter,
 * built from its reflected type (whatever those parameters are on the
 * installed version — nothing is hardcoded about which exist). Each stand-in
 * implements a small, version-stable interface (PSR-11, PSR-14, or TYPO3's
 * own cache FrontendInterface) directly, with no framework or test-library
 * dependency; every method other than the one actually exercised (the cache
 * frontend's get()) throws, since none of them are ever called. The given
 * Icon is pre-seeded into whichever caching mechanism getIcon() checks
 * first, so it returns immediately without ever needing the rest of those
 * dependencies (icon registry lookups, DI container, ...) to behave
 * realistically.
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
    public static function create(string $identifier, ?Icon $iconToReturn = null): IconFactory
    {
        $iconToReturn ??= new Icon();
        $reflectionClass = new ReflectionClass(IconFactory::class);
        $hasInjectedCache = false;

        $arguments = [];
        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : '';

            if (is_a($typeName, FrontendInterface::class, true)) {
                $hasInjectedCache = true;
            }

            $arguments[$parameter->getPosition()] = match (true) {
                is_a($typeName, EventDispatcherInterface::class, true) => self::eventDispatcher(),
                is_a($typeName, ContainerInterface::class, true) => self::container(),
                is_a($typeName, FrontendInterface::class, true) => self::cache($iconToReturn),
                is_a($typeName, IconRegistry::class, true) => (new ReflectionClass(IconRegistry::class))->newInstanceWithoutConstructor(),
                default => null,
            };
        }

        $iconFactory = $reflectionClass->newInstanceArgs($arguments);

        if (!$hasInjectedCache) {
            // TYPO3 v12.4: no injected cache, IconFactory keeps its own
            // static in-memory result array instead. Pre-seed the exact
            // entry getIcon() will look up, mirroring its cache-key formula
            // (identifier + size + overlayIdentifier + state, all default
            // here since the only production call site passes just an
            // identifier and Icon::SIZE_SMALL).
            // md5() here isn't a hashing decision of ours: it must reproduce
            // IconFactory::getIcon()'s own v12.4 cache-key algorithm exactly,
            // or the pre-seeded entry is never found.
            $reflectionClass->getProperty('iconCache')->setValue(null, [
                md5($identifier.Icon::SIZE_SMALL.'') => $iconToReturn,
            ]);
        }

        return $iconFactory;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            public function dispatch(object $event): never
            {
                throw new RuntimeException('Unused in this test double.', 3898636729);
            }
        };
    }

    private static function container(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): never
            {
                throw new class('Unused in this test double.', 6121301550) extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): never
            {
                throw new RuntimeException('Unused in this test double.', 3892199414);
            }
        };
    }

    private static function cache(Icon $iconToReturn): FrontendInterface
    {
        return new class($iconToReturn) implements FrontendInterface {
            public function __construct(private readonly Icon $icon) {}

            public function getIdentifier(): never
            {
                throw new RuntimeException('Unused in this test double.', 5575070358);
            }

            public function getBackend(): never
            {
                throw new RuntimeException('Unused in this test double.', 7249173311);
            }

            public function set($entryIdentifier, $data, array $tags = [], $lifetime = null): never
            {
                throw new RuntimeException('Unused in this test double.', 2060856579);
            }

            public function get($entryIdentifier): Icon
            {
                return $this->icon;
            }

            public function has($entryIdentifier): never
            {
                throw new RuntimeException('Unused in this test double.', 7079020823);
            }

            public function remove($entryIdentifier): never
            {
                throw new RuntimeException('Unused in this test double.', 7497598959);
            }

            public function flush(): never
            {
                throw new RuntimeException('Unused in this test double.', 8855853341);
            }

            public function flushByTag($tag): never
            {
                throw new RuntimeException('Unused in this test double.', 9617534387);
            }

            public function flushByTags(array $tags): never
            {
                throw new RuntimeException('Unused in this test double.', 2232286540);
            }

            public function collectGarbage(): never
            {
                throw new RuntimeException('Unused in this test double.', 6964247015);
            }

            public function isValidEntryIdentifier($identifier): never
            {
                throw new RuntimeException('Unused in this test double.', 8971036255);
            }

            public function isValidTag($tag): never
            {
                throw new RuntimeException('Unused in this test double.', 8439018922);
            }
        };
    }
}
