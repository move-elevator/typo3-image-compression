# Configuration

All settings live in **Admin Tools > Settings > Extension Configuration > typo3_image_compression**.

Each option below lists its internal key, type, and default.

## Choosing a provider

| Provider | Tools | Compression | Cost | Best for |
|----------|-------|-------------|------|----------|
| [`tinify`](#tinify-tinypng-api) | TinyPNG API | ~70–80% | API quota | Production, best quality |
| [`local-tools`](#local-tools-optimized-tools) | jpegoptim, optipng, pngquant, gifsicle, cwebp, avifenc | ~50–60% | Free | Self-hosted, no API costs |
| [`local-basic`](#local-basic-imagemagick--graphicsmagick) | ImageMagick / GraphicsMagick | ~30–40% | Free | JPEG only, quick setup |

### `tinify` (TinyPNG API)

1. Register at [TinyPNG Developers](https://tinypng.com/developers) to obtain your API key.
2. Set **Provider** to `tinify` and enter your API key.
3. Free tier: **500 compressions/month** — upgrades available via the [TinyPNG dashboard](https://tinypng.com/dashboard).

> [!WARNING]
> The free API limit (500 compressions/month) can be exhausted quickly on large sites with many existing images. Use the CLI `--include-processed` flag with caution.

### `local-tools` (optimized tools)

Install the required tools on your server:

```bash
# Debian/Ubuntu
apt install jpegoptim optipng pngquant gifsicle webp libavif-bin

# macOS (Homebrew)
brew install jpegoptim optipng pngquant gifsicle webp libavif
```

Set **Provider** to `local-tools`. The extension auto-detects available tools per file's MIME type and skips a file if no matching tool is installed:

| MIME type | Tool |
|-----------|------|
| `image/jpeg` | `jpegoptim` |
| `image/png` | `optipng`, falling back to `pngquant` |
| `image/gif` | `gifsicle` |
| `image/webp` | `cwebp` |
| `image/avif` | `avifenc` |

### `local-basic` (ImageMagick / GraphicsMagick)

No additional installation needed, uses TYPO3's configured graphics processor (`$TYPO3_CONF_VARS['GFX']['processor']`). Set **Provider** to `local-basic`.

Only `image/jpeg` is compressed. PNG and GIF are intentionally excluded: ImageMagick/GraphicsMagick typically *increases* file size when reprocessing these formats. Use `local-tools` for PNG.

## Quality settings

Name: `jpegQuality`, `pngQuality`, `webpQuality` · Type: int (1–100) · Default: `85`, `85`, `80`

Quality for local compression (`local-tools` and `local-basic`), applied independently per format.

> [!NOTE]
> `avifenc` has no dedicated quality setting: AVIF compression reuses `webpQuality`.

## `mimeTypes`

Name: `mimeTypes` · Type: comma-separated string · Default: `image/avif,image/jpeg,image/png,image/webp`

Which MIME types are eligible for compression, checked by every provider before it touches a file. **GIF is not in the default list** even though `local-tools` can compress it via `gifsicle` — add `image/gif` explicitly if you want GIFs compressed.

## `excludeFolders`

Name: `excludeFolders` · Type: comma-separated string · Default: *(empty)*

File identifiers starting with any of these prefixes are skipped, for every provider. Example: `1:/no-compression/,2:/imports/`.

## `debug`

Name: `debug` · Type: bool · Default: `false`

When enabled on the `tinify` provider, files are matched and logged as if they would be compressed, but the TinyPNG API is never called. Useful for verifying MIME type and exclude-folder rules without spending API quota. Has no effect on `local-tools` or `local-basic`.

## `systemInformationToolbar`, `showCompressionStatus`, `showStatusReport`

Name: `systemInformationToolbar`, `showCompressionStatus`, `showStatusReport` · Type: bool · Default: `false`, `true`, `true`

Toggle the [backend integration](usage.md#backend-integration) pieces independently: the toolbar item, the compression status column, and the System Report.

<details>
<summary>All options as a TYPO3 <code>EXTENSIONS</code> configuration array</summary>

```php
// config/system/additional.php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['typo3_image_compression'] = [
    'provider' => 'tinify',
    'apiKey' => getenv('TINIFY_API_KEY') ?: '',
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
```

</details>

## See also

- [Usage](usage.md) — the CLI command and where the values above show up in the backend
