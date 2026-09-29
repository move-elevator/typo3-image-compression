# AGENTS.md

## Project overview

TYPO3 extension (`typo3_image_compression`) that automatically compresses images uploaded to the TYPO3 backend, using the TinyPNG API or local tools (optimized tools, ImageMagick/GraphicsMagick). It also offers a batch CLI command, backup and restore of originals, and backend integration (toolbar statistics, file metadata, system report).

- Package: `move-elevator/typo3-image-compression`, namespace `MoveElevator\Typo3ImageCompression` (PSR-4, `Classes/`)
- Requirements: PHP 8.2 to 8.5, TYPO3 12.4, 13.4 and 14.3, `ext-fileinfo`
- Only FAL storages with the built-in `Local` driver are compressed

## Structure

- `Classes/Compression/` `CompressorInterface` and providers (`TinifyCompressor`, `LocalToolsCompressor`, `LocalBasicCompressor`), `CompressorFactory`, `CompressorChain`, optional capability interfaces (quota, availability, MIME type aware)
- `Classes/Message/` and `Classes/MessageHandler/` `CompressImageMessage` and its handler (Symfony Messenger)
- `Classes/EventListener/` upload, replace, file list and toolbar listeners, `Classes/Event/` before and after compression events
- `Classes/Command/` `imagecompression:compressImages`, restore and prune backups commands
- `Classes/Backup/`, `Classes/Backend/`, `Classes/ContextMenu/`, `Classes/Controller/` backup, restore and backend UI
- `Classes/Domain/`, `Classes/Resource/`, `Classes/Report/`, `Classes/Updates/`, `Classes/Utility/`, `Classes/Configuration/`, `Classes/Form/`
- `Configuration/` `Services.yaml`, `TCA/`, `Backend/`, `Extbase/`, `JavaScriptModules.php`
- `Resources/` templates, language files, JavaScript
- `Tests/Unit/` and `Tests/Functional/` PHPUnit tests, mirror `Classes/`
- `Tests/CGL/` separate Composer project with code style, static analysis and migration tooling
- `docs/` user documentation (`configuration.md`, `usage.md`)
- `.ddev/` DDEV setup and commands to install TYPO3 test instances

Architecture notes:

- Compression runs through a provider pattern. `Services.yaml` builds `CompressorInterface` via `CompressorFactory`. To add a provider, implement the interfaces and register it there.
- Upload and replace listeners dispatch a `CompressImageMessage` through `MessageBusInterface`. `CompressImageMessageHandler` (tagged `messenger.message_handler`) compresses. The default transport is synchronous, projects can route it to an async transport.
- Event listeners and commands are registered in `Services.yaml`, not with PHP attributes, to stay compatible with TYPO3 12.4.
- `RestoreFileProvider` (context menu) needs a priority below 100, the fixed priority of the core `FileProvider`. An equal priority silently overwrites that provider.
- `RestoreFileController` is registered `public: true` because TYPO3 resolves backend routes through the container. The restore UI submits via a detached `<form>` built in JavaScript, never a form nested in the file list markup.
- The file list restore button (`AfterFileListRendered`) only works on TYPO3 12.4 and 13.4, the event changed in 14.

## Development commands

The project uses DDEV. Prefix commands with `ddev`.

```bash
ddev start
ddev composer install
ddev install all      # or: ddev install 13
ddev 13 typo3 cache:flush
```

Lint, fix, static analysis and migration run through `ddev cgl`, which executes the scripts of `Tests/CGL/composer.json`:

```bash
ddev cgl lint       # composer, editorconfig, php
ddev cgl fix        # composer, editorconfig, php
ddev cgl sca        # PHPStan
ddev cgl migration  # Rector
```

Single linters: `ddev cgl lint:composer`, `lint:editorconfig`, `lint:php`. Matching fixers: `fix:composer`, `fix:editorconfig`, `fix:php`.

## Testing

PHPUnit with unit tests (`phpunit.xml`) and functional tests (`FunctionalTests.xml`, TYPO3 testing framework). Test classes use PHPUnit attributes (`#[Test]`, `#[CoversClass]`).

```bash
ddev composer test              # unit and functional
ddev composer test:unit         # without coverage
ddev composer test:functional
ddev composer test:coverage     # both suites, merged with phpcov into .Build/coverage/
```

CI runs the shared `tests-typo3` workflow on TYPO3 12.4, 13.4 and 14.3, PHP 8.2 to 8.5, with `highest` and `lowest` dependencies. The CGL workflow runs the linters on every push.

## Code style and static analysis

- PHP CS Fixer with `konradmichalik/php-cs-fixer-preset`, config in `Tests/CGL/.php-cs-fixer.php`. The fixer generates the license header, run `ddev cgl fix:php`
- `declare(strict_types=1);` in every PHP file
- PHPStan level 8 with baseline, config in `Tests/CGL/phpstan.neon`
- Rector config in `Tests/CGL/rector.php`
- `composer-dependency-analyser` and `composer-require-checker` check dependencies
- EditorConfig is enforced via `.editorconfig`
- Secret scanning ignores are listed in `.gitleaksignore`

## Git workflow

- Commit format: `<type>: <description>` with type one of `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`
- Single-line messages, no co-author trailers
- One commit per logical change, open a pull request against `main`
