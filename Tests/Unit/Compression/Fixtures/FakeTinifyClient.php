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

namespace MoveElevator\Typo3ImageCompression\Tests\Unit\Compression\Fixtures;

use Throwable;
use Tinify\Client;

use function count;

/**
 * FakeTinifyClient.
 *
 * Extends the SDK client so it satisfies the Tinify::setClient() type. Only
 * request() is overridden, so no HTTP call ever leaves the test.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class FakeTinifyClient extends Client
{
    /**
     * @var array<int, mixed>
     */
    public array $requestBodies = [];

    private function __construct(
        private readonly string $compressedBody,
        private readonly ?Throwable $failure,
    ) {
        parent::__construct('fake-key-for-test');
    }

    public static function compressingTo(string $compressedBody): self
    {
        return new self($compressedBody, null);
    }

    public static function failingWith(Throwable $failure): self
    {
        return new self('', $failure);
    }

    /**
     * @param mixed $method
     * @param mixed $url
     * @param mixed $body
     */
    public function request($method, $url, $body = null): object
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $this->requestBodies[] = $body;

        // First call is the "/shrink" upload, which responds with a Location
        // header pointing at the compressed result; the second is the download.
        if (1 === count($this->requestBodies)) {
            return (object) ['headers' => ['location' => 'https://fake.tinify.test/output/abc'], 'body' => ''];
        }

        return (object) ['headers' => [], 'body' => $this->compressedBody];
    }
}
