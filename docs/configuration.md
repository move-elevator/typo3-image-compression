# Configuration

All settings live in **Admin Tools > Settings > Extension Configuration > typo3_image_compression**.

Each option below lists its internal key, type, and default.

## Choosing a provider

| Provider | Tools | Compression | Cost | Best for |
|----------|-------|-------------|------|----------|
| [`tinify`](#tinify-tinypng-api) | TinyPNG API | ~70–80% | API quota | Production, best quality |
| [`local-tools`](#local-tools-optimized-tools) | jpegoptim, optipng, pngquant, gifsicle, cwebp, avifenc | ~50–60% | Free | Self-hosted, no API costs |
| [`local-basic`](#local-basic-imagemagick--graphicsmagick) | ImageMagick / GraphicsMagick | ~30–40% | Free | JPEG only, quick setup |

**Provider** also accepts a comma-separated, ordered list for fallback: `tinify,local-tools` uses `tinify` first, falling back to `local-tools` for a MIME type `tinify` cannot handle or once the TinyPNG quota is exhausted. A single value, the default, behaves exactly as before.

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

## `minimumSavingPercent`

Name: `minimumSavingPercent` · Type: int (0–100) · Default: `5`

Every provider compresses into a temporary file first and compares its size against the original before replacing anything. A result that saves less than this percentage is discarded, the original is kept, and the file is marked as already optimal instead of compressed, so it is not retried on every run.

## Metadata

Name: `preserveCopyright`, `preserveCreationDate`, `preserveColorProfile` · Type: bool · Default: `false`

By default, compression strips all image metadata: EXIF, IPTC, XMP and the embedded ICC color profile. GPS location data is always stripped and cannot be preserved for `tinify` and `local-basic`, publishing where a photo was taken is a data protection concern.

For press, stock or agency images where the copyright tag matters, or source images authored in a wide-gamut color space (e.g. Adobe RGB) where dropping the ICC profile shifts colors, enable:

| Setting | Effect |
|---------|--------|
| `preserveCopyright` | Keeps the EXIF/IPTC copyright tag |
| `preserveCreationDate` | Keeps the EXIF/IPTC creation date |
| `preserveColorProfile` | Keeps the embedded ICC color profile |

Support depends on the provider:

- `tinify` preserves copyright and creation date independently via the TinyPNG API. `preserveColorProfile` has no effect: TinyPNG always converts images to sRGB and offers no ICC-preservation option.
- `local-tools` (jpegoptim, JPEG only) preserves the color profile independently (`--strip-icc`). Copyright and creation date are not independent: jpegoptim can only strip the whole EXIF or IPTC block, not individual tags, so enabling either setting keeps both fields, and any other EXIF/IPTC data including GPS.
- `local-basic` (ImageMagick/GraphicsMagick) can only preserve the color profile on its own; enabling copyright or creation date preservation keeps the whole EXIF/IPTC block too, since plain `convert` has no per-tag strip flag, except GPS position tags, which are always explicitly cleared regardless of the other settings.

## Backup & restore

Name: `enableBackup`, `backupRetentionDays` · Type: bool, int (days) · Default: `false`, `30`

When `enableBackup` is on, the original file is copied outside FAL (`var/image_compression/backup/`) before every compression, so it can be [restored](usage.md#backup--restore) later. `backupRetentionDays` controls how long backups are kept before `imagecompression:pruneBackups` deletes them; `0` keeps them indefinitely.

## `commandTimeout`

Name: `commandTimeout` · Type: int (seconds) · Default: `60`

For local providers, limits how long an external tool invocation (`jpegoptim`, `optipng`, ImageMagick, ...) may run before it is killed. A timed-out invocation is logged and no compression status is recorded. Local tools compress in place, so a process killed mid-write can leave a partially written file, the same risk that already exists for any other abrupt interruption of these tools (crash, OOM kill), not something specific to the timeout feature.

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
    'minimumSavingPercent' => 5,
    'preserveCopyright' => false,
    'preserveCreationDate' => false,
    'preserveColorProfile' => false,
    'commandTimeout' => 60,
    'enableBackup' => false,
    'backupRetentionDays' => 30,
    'systemInformationToolbar' => true,
    'showCompressionStatus' => true,
    'showStatusReport' => true,
];
```

</details>

## See also

- [Usage](usage.md) — the CLI command and where the values above show up in the backend
