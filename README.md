<div align="center">

![Extension icon](Resources/Public/Icons/Extension.svg)

# TYPO3 extension `typo3_image_compression`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![Supported TYPO3 versions](https://typo3-badges.dev/badge/typo3_image_compression/typo3/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![Coverage](https://img.shields.io/coverallsCoverage/github/move-elevator/typo3-image-compression?logo=coveralls)](https://coveralls.io/github/move-elevator/typo3-image-compression)
[![CGL](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/cgl.yml?label=cgl&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/tests.yml?label=tests&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/move-elevator/typo3-image-compression/license)](LICENSE.md)

</div>

This TYPO3 extension automatically compresses images uploaded to the TYPO3 backend. Choose between the TinyPNG API for best results or local tools for cost-free compression.

## Features

- **Multiple compression providers**: TinyPNG API, local optimized tools, or ImageMagick/GraphicsMagick
- Automatic compression of JPG, PNG, GIF, AVIF and WebP images on upload
- CLI command for batch processing existing images
- Configurable quality settings for local compression
- Image compression statistic in the system information toolbar
- Image compression info in sys_file_metadata edit view

## Provider Comparison

| Provider | Tools | Compression | Cost | Best For |
|----------|-------|-------------|------|----------|
| `tinify` | TinyPNG API | ~70-80% | API quota | Production, best quality |
| `local-tools` | jpegoptim, optipng, pngquant, gifsicle, cwebp | ~50-60% | Free | Self-hosted, no API costs |
| `local-basic` | ImageMagick / GraphicsMagick | ~30-40% | Free | JPEG only, quick setup |

## 🔥 Installation

### Requirements

* TYPO3 >= 12.4
* PHP 8.2+

### Composer

[![Packagist](https://img.shields.io/packagist/v/move-elevator/typo3-image-compression?label=version&logo=packagist)](https://packagist.org/packages/move-elevator/typo3-image-compression)
[![Packagist Downloads](https://img.shields.io/packagist/dt/move-elevator/typo3-image-compression?color=brightgreen)](https://packagist.org/packages/move-elevator/typo3-image-compression)

```bash
composer require move-elevator/typo3-image-compression
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![TER downloads](https://typo3-badges.dev/badge/typo3_image_compression/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/typo3_image_compression).

## 🧰 Configuration

Configure the extension in **Admin Tools > Settings > Extension Configuration**.

### Provider: `tinify` (TinyPNG API)

1. Register at [TinyPNG Developers](https://tinypng.com/developers) to obtain your API key
2. Set **Provider** to `tinify` and enter your API key
3. Free tier: **500 compressions/month**, paid upgrades via [TinyPNG dashboard](https://tinypng.com/dashboard)

> [!WARNING]
> Be aware that the free API limit (500 compressions/month) can be exhausted quickly on large sites with many existing images.
> 
### Provider: `local-tools` (Optimized Tools)

Install the required tools on your server:

```bash
# Debian/Ubuntu
apt install jpegoptim optipng pngquant gifsicle webp

# macOS (Homebrew)
brew install jpegoptim optipng pngquant gifsicle webp
```

Set **Provider** to `local-tools`. The extension auto-detects available tools.

### Provider: `local-basic` (ImageMagick/GraphicsMagick)

No additional installation needed - uses TYPO3's configured graphics processor. Set **Provider** to `local-basic`.

### Quality Settings

For local providers, configure quality (1-100) for JPEG, PNG, and WebP compression.

## Usage

### Automatic Compression

Once configured, all images (with supported mime type) uploaded via the TYPO3 backend will be automatically compressed.

### Batch Processing (CLI)

For existing projects, a CLI command is available to compress images that were uploaded before the extension was installed.

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

| Argument/Option | Description |
|-----------------|-------------|
| `limit` | Number of images to process (default: 100) |
| `--include-processed`, `-p` | Also compress processed files (thumbnails, crops). By default, only original files are compressed to save API quota. |
| `--retry-errors`, `-r` | Retry compression for files that previously failed. On success, the error status is cleared. |

> [!TIP]
> When using **Tinify** (TinyPNG API), we recommend **not** using `--include-processed` to conserve your API quota. Processed files are regenerated from the already-compressed originals.

> [!IMPORTANT]
> Before running the CLI command, ensure your TYPO3 file index is up to date. Use the scheduler task **"File Abstraction Layer: Update storage index"** to update the index.

## 💛 Acknowledgements

This project is a fork and further development of the great [tinyimg](https://github.com/schmitzal/tinyimg) extension.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).