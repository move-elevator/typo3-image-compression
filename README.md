<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_image_compression`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
![TYPO3](https://img.shields.io/badge/TYPO3-12.4%20%7C%2013.4%20%7C%2014.3-orange.svg)
[![Coverage](https://img.shields.io/coverallsCoverage/github/move-elevator/typo3-image-compression?logo=coveralls)](https://coveralls.io/github/move-elevator/typo3-image-compression)
[![CGL](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/cgl.yml?label=cgl&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/move-elevator/typo3-image-compression/tests.yml?label=tests&logo=github)](https://github.com/move-elevator/typo3-image-compression/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/move-elevator/typo3-image-compression?label=packagist&logo=packagist)](https://packagist.org/packages/move-elevator/typo3-image-compression)
[![License](https://poser.pugx.org/move-elevator/typo3-image-compression/license)](LICENSE.md)

</div>

This TYPO3 extension automatically compresses images uploaded to the TYPO3 backend. Choose between the TinyPNG API for best results or local tools for cost-free compression.

> [!NOTE]
> Uncompressed images are one of the most common causes of slow-loading TYPO3 websites. This extension compresses images automatically as part of the file processing pipeline, so editors don't have to remember to optimize every upload manually.

## ✨ Features

- **[Multiple compression providers](docs/configuration.md)**: TinyPNG API, local optimized tools, or ImageMagick/GraphicsMagick
- Automatic compression of JPG, PNG, WebP and AVIF images on upload — GIF support exists but is [off by default](docs/configuration.md#mimetypes)
- **[CLI command](docs/usage.md)** for batch processing existing images
- **[Quality settings](docs/configuration.md#quality-settings)** for local compression
- **[Backend integration](docs/usage.md#backend-integration)**: compression statistics in the system information toolbar, per-file status in the file metadata edit view, and a System Report with per-provider statistics

## 🔥 Installation

### Requirements

- TYPO3 >= 12.4
- PHP >= 8.2

### Composer

[![Packagist Downloads](https://img.shields.io/packagist/dt/move-elevator/typo3-image-compression?logo=packagist)](https://packagist.org/packages/move-elevator/typo3-image-compression)

```bash
composer require move-elevator/typo3-image-compression
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_image_compression/version/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)
[![TER downloads](https://typo3-badges.dev/badge/typo3_image_compression/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_image_compression)

Download the zip file from the [TYPO3 Extension Repository (TER)](https://extensions.typo3.org/extension/typo3_image_compression).

## 🚀 Quick start

```bash
composer require move-elevator/typo3-image-compression
```

Set **Provider** to `tinify` and paste your [TinyPNG API key](https://tinypng.com/developers) in **Admin Tools > Settings > Extension Configuration**. That's it: the next image uploaded to the TYPO3 backend is compressed automatically.

## ⚙️ Configuration

Configure the extension in **Admin Tools > Settings > Extension Configuration**.

| Provider | Tools | Compression | Cost | Best for |
|----------|-------|-------------|------|----------|
| [`tinify`](docs/configuration.md#tinify-tinypng-api) | TinyPNG API | ~70–80% | API quota | Production, best quality |
| [`local-tools`](docs/configuration.md#local-tools-optimized-tools) | jpegoptim, optipng, pngquant, gifsicle, cwebp, avifenc | ~50–60% | Free | Self-hosted, no API costs |
| [`local-basic`](docs/configuration.md#local-basic-imagemagick--graphicsmagick) | ImageMagick / GraphicsMagick | ~30–40% | Free | JPEG only, quick setup |

> [!WARNING]
> The `tinify` free tier is limited to **500 compressions/month**. Use the CLI `--include-processed` flag with caution on large sites with many existing images.

See the [configuration reference](docs/configuration.md) for provider setup, every extension configuration option, and quality tuning.

## 📚 Documentation

| Topic | What's inside |
|-------|----------------|
| [Configuration](docs/configuration.md) | Provider setup (`tinify`, `local-tools`, `local-basic`), every extension configuration option, and quality tuning |
| [Usage](docs/usage.md) | The `imagecompression:compressImages` CLI command and its options, plus backend integration (toolbar, reports, file metadata) |

## 🧑‍💻 Contributing

Please refer to [`CONTRIBUTING.md`](CONTRIBUTING.md).

## 💎 Credits

This project is a fork and further development of the great [tinyimg](https://github.com/schmitzal/tinyimg) extension.

## ⭐ License

This project is licensed under the [GNU General Public License 2.0 (or later)](LICENSE.md).
