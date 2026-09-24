# Usage

Once configured, all images with a supported MIME type uploaded via the TYPO3 backend are compressed automatically. The sections below cover automatic compression, batch processing existing images, and the backend UI surfaces the extension adds.

## Automatic compression

By default, compression runs synchronously within the upload request. To run it on a queue worker instead (recommended with the `tinify` provider, so an editor's upload does not wait on a round trip to the TinyPNG API), route `MoveElevator\Typo3ImageCompression\Message\CompressImageMessage` to an async [Messenger transport](https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/MessageBus/Index.html), for example:

```php
// config/system/additional.php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][\MoveElevator\Typo3ImageCompression\Message\CompressImageMessage::class] = 'doctrine';
```

With that in place, run `vendor/bin/typo3 messenger:consume doctrine` (typically as a scheduler task) to process compressions in the background.

## `imagecompression:compressImages`

> [!IMPORTANT]
> Before running the CLI command, ensure your TYPO3 file index is up to date. Run the scheduler task **"File Abstraction Layer: Update storage index"** first.

```bash
vendor/bin/typo3 imagecompression:compressImages [<limit>] [-p|--include-processed] [-r|--retry-errors] [-d|--dry-run] [-s|--storage=<uid>] [--folder=<path>]
```

Compresses images that were uploaded before the extension was installed, or that were skipped by an earlier run. The page cache is flushed once per run, only if at least one file was compressed. The run's summary reports compressed, skipped (excluded folder, unsupported MIME type, no local tool available, below the minimum saving threshold) and failed files separately, so it distinguishes "nothing to do" from "something went wrong".

```bash
# Compress up to 100 original images (default)
vendor/bin/typo3 imagecompression:compressImages
```

### `limit`

Number of files to process. Default: `100`.

```bash
vendor/bin/typo3 imagecompression:compressImages 50
```

### `--include-processed`, `-p`

Also compress processed files (thumbnails, crops, etc.), in addition to originals. Omit this flag to save API quota: processed files are regenerated from already-compressed originals anyway.

```bash
vendor/bin/typo3 imagecompression:compressImages --include-processed
```

### `--retry-errors`, `-r`

Retry compression for files that previously failed, clearing the error status on success.

```bash
vendor/bin/typo3 imagecompression:compressImages --retry-errors
```

<details>
<summary>Combining all options</summary>

```bash
vendor/bin/typo3 imagecompression:compressImages 200 --include-processed --retry-errors
```

</details>

> [!TIP]
> When using the `tinify` provider, omit `--include-processed` to conserve your monthly API quota.

## Backup & restore

Compression overwrites the original file in place. Enable [`enableBackup`](configuration.md#backup--restore) to keep a copy outside FAL (`var/image_compression/backup/`, not indexed, not shown in the file list) before every compression, so it can be restored later.

On TYPO3 v12.4/v13.4, a restore button appears in the file list for any file with a backup. From the CLI:

```bash
vendor/bin/typo3 imagecompression:restore <uid>
vendor/bin/typo3 imagecompression:restore --all
```

Restores the given file (or every file with a backup) and rebuilds its derivatives.

```bash
vendor/bin/typo3 imagecompression:pruneBackups [--dry-run]
```

Deletes backups older than [`backupRetentionDays`](configuration.md#backup--restore). `--dry-run` reports how many backups would be deleted without deleting them. A retention of `0` disables pruning, backups are then kept indefinitely.

## Backend integration

- **Upload progress** — the file list's drag-uploader shows a "Compressing…" label while a JPEG or PNG upload is being processed.
- **System information toolbar** — shows current TinyPNG API usage (`compressed / limit`). Only appears when the `tinify` provider is active, an API key is configured, and [`systemInformationToolbar`](configuration.md#systeminformationtoolbar-showcompressionstatus-showstatusreport) is enabled.
- **System Reports** (`Admin Tools > System Reports`) — the active provider, compression statistics for original and processed files, and (with `tinify`) API usage. Controlled by [`showStatusReport`](configuration.md#systeminformationtoolbar-showcompressionstatus-showstatusreport).
- **File metadata** (`sys_file_metadata`) — per-file compression status and error messages, next to the file's other metadata. Controlled by [`showCompressionStatus`](configuration.md#systeminformationtoolbar-showcompressionstatus-showstatusreport).

## See also

- [Configuration](configuration.md) — providers, every extension configuration option, and quality tuning
