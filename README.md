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

> [!NOTE]
> This extension is a fork of the original extension [tinyimg](https://github.com/schmitzal/tinyimg).

## About

This TYPO3 extension automatically compresses PNG and JPG images uploaded to the TYPO3 backend using the [TinyPNG API](https://tinypng.com/). The compression can reduce file sizes by up to 80% while maintaining excellent image quality.

### Features

- Automatic compression of JPG and PNG images on upload
- Uses TinyPNG's powerful compression API
- CLI command for batch processing existing images
- Configurable via extension settings
- Image compression statistic in the system information toolbar

## Installation

### Requirements

| Requirement | Version |
|-------------|---------|
| TYPO3       | >= 12.4 |
| PHP         | >= 8.2  |

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

## Configuration

1. **Get an API key**: Register at [TinyPNG Developers](https://tinypng.com/developers) to obtain your API key
2. **Configure the extension**: Enter your API key in the extension settings (Admin Tools > Settings > Extension Configuration)
3. **Activate the extension**: Enable the extension in your TYPO3 installation

> [!TIP]
> Consider disabling compression during development or testing to preserve your API quota.

### API Limits

The free TinyPNG tier includes **500 compressions per month**. For higher volumes, paid upgrades are available through your [TinyPNG dashboard](https://tinypng.com/dashboard).

## Usage

### Automatic Compression

Once configured, all JPG and PNG images uploaded via the TYPO3 backend will be automatically compressed.

### Batch Processing (CLI)

For existing projects, a CLI command is available to compress images that were uploaded before the extension was installed. The command processes 100 images per execution across all file storages.

> [!IMPORTANT]
> Before running the CLI command, ensure your TYPO3 file index is up to date. Use the scheduler task **"File Abstraction Layer: Update storage index"** to update the index.

> [!WARNING]
> Be aware that the free API limit (500 compressions/month) can be exhausted quickly on large sites with many existing images.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).