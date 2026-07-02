<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_image_compression`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![Supported TYPO3 versions](https://typo3-badges.dev/badge/typo3_image_compression/typo3/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![Coverage](https://img.shields.io/coverallsCoverage/github/move-elevator/typo3-image-compression?logo=coveralls)](https://coveralls.io/github/move-elevator/typo3-image-compression)
[![CGL](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/cgl.yml?label=cgl&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/tests.yml?label=tests&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/move-elevator/typo3-image-compression?label=packagist&logo=packagist)](https://packagist.org/packages/move-elevator/typo3-image-compression)
[![License](https://poser.pugx.org/move-elevator/typo3-image-compression/license)](LICENSE.md)

</div>

This TYPO3 extension automatically compresses images uploaded to the TYPO3 backend. Choose between the TinyPNG API for best results or local tools for cost-free compression.

## ✨ Features

- **Multiple compression providers**: TinyPNG API, local optimized tools, or ImageMagick/GraphicsMagick
- Automatic compression of JPG, PNG, GIF, AVIF and WebP images on upload
- CLI command for batch processing existing images
- Configurable quality settings for local compression
- Image compression statistics in the system information toolbar
- Compression status visible in sys_file_metadata edit view
- System report with per-provider statistics in Admin Tools

## 🔥 Installation

**Requirements:** TYPO3 >= 12.4 · PHP 8.2+

### Composer

```bash
composer require move-elevator/typo3-image-compression
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![TER downloads](https://typo3-badges.dev/badge/typo3_image_compression/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)

Download the zip file from the [TYPO3 Extension Repository (TER)](https://extensions.typo3.org/extension/typo3_image_compression).

## ⚙️ Configuration

Configure the extension in **Admin Tools > Settings > Extension Configuration**.

### Provider overview

| Provider | Tools | Compression | Cost | Best for |
|----------|-------|-------------|------|----------|
| `tinify` | TinyPNG API | ~70–80% | API quota | Production, best quality |
| `local-tools` | jpegoptim, optipng, pngquant, gifsicle, cwebp | ~50–60% | Free | Self-hosted, no API costs |
| `local-basic` | ImageMagick / GraphicsMagick | ~30–40% | Free | JPEG only, quick setup |

### `tinify` (TinyPNG API)

1. Register at [TinyPNG Developers](https://tinypng.com/developers) to obtain your API key.
2. Set **Provider** to `tinify` and enter your API key.
3. Free tier: **500 compressions/month** — upgrades available via the [TinyPNG dashboard](https://tinypng.com/dashboard).

> [!WARNING]
> The free API limit (500 compressions/month) can be exhausted quickly on large sites with many existing images. Use the CLI `--include-processed` flag with caution.

### `local-tools` (Optimized tools)

Install the required tools on your server:

```bash
# Debian/Ubuntu
apt install jpegoptim optipng pngquant gifsicle webp

# macOS (Homebrew)
brew install jpegoptim optipng pngquant gifsicle webp
```

Set **Provider** to `local-tools`. The extension auto-detects available tools.

### `local-basic` (ImageMagick / GraphicsMagick)

No additional installation needed — uses TYPO3's configured graphics processor. Set **Provider** to `local-basic`.

### Quality settings

For local providers, configure quality (1–100) for JPEG, PNG, and WebP compression independently.

## 💡 Usage

### Automatic compression

Once configured, all images with a supported MIME type uploaded via the TYPO3 backend are automatically compressed.

### Batch processing (CLI)

Use the CLI command to compress images that were uploaded before the extension was installed.

> [!IMPORTANT]
> Before running the CLI command, ensure your TYPO3 file index is up to date. Run the scheduler task **"File Abstraction Layer: Update storage index"** first.

```bash
# Compress up to 100 original images (default)
vendor/bin/typo3 imagecompression:compressImages

# Compress up to 50 images
vendor/bin/typo3 imagecompression:compressImages 50

# Also compress processed files (thumbnails, crops, etc.)
vendor/bin/typo3 imagecompression:compressImages --include-processed

# Retry failed compressions
vendor/bin/typo3 imagecompression:compressImages --retry-errors

# Combine options
vendor/bin/typo3 imagecompression:compressImages 200 --include-processed --retry-errors
```

| Argument / Option | Description |
|-------------------|-------------|
| `limit` | Number of images to process (default: 100) |
| `--include-processed`, `-p` | Also compress processed files (thumbnails, crops). Omit to save API quota — processed files are regenerated from already-compressed originals. |
| `--retry-errors`, `-r` | Retry compression for files that previously failed. Clears error status on success. |

> [!TIP]
> When using the `tinify` provider, omit `--include-processed` to conserve your monthly API quota. Processed files are regenerated from the already-compressed originals anyway.

### Backend integration

- **System information toolbar** — displays current API usage (TinyPNG) or compression statistics.
- **System Reports** (`Admin Tools > System Reports`) — active provider, per-file-type statistics, and API usage.
- **File metadata** (`sys_file_metadata`) — per-file compression status and error messages.

## 🙏 Acknowledgments

This project is a fork and further development of the great [tinyimg](https://github.com/schmitzal/tinyimg) extension.

## 🧑‍💻 Contributing

Please refer to [`CONTRIBUTING.md`](CONTRIBUTING.md).

## 📜 License

This project is licensed under the [GNU General Public License 2.0 (or later)](LICENSE.md).
