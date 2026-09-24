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

namespace MoveElevator\Typo3ImageCompression\ContextMenu\ItemProviders;

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3ImageCompression\Configuration\ExtensionConfiguration;
use MoveElevator\Typo3ImageCompression\Controller\RestoreFileController;
use MoveElevator\Typo3ImageCompression\Domain\Repository\FileRepository;
use MoveElevator\Typo3ImageCompression\Resource\FileResolver;
use TYPO3\CMS\Backend\ContextMenu\ItemProviders\ProviderInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * RestoreFileProvider.
 *
 * Adds a "Restore original file" entry to TYPO3's native right-click context
 * menu for sys_file records, the counterpart to AfterFileListRendered's
 * RestoreButton in Filelist's List-view "More options" dropdown, which is
 * the menu's only other entry point and the only one available in Grid/Tiles
 * view.
 *
 * Implements ProviderInterface directly rather than extending
 * TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider: that base
 * class is built around a static $itemsConfiguration array rendered through
 * IconFactory, which is exactly the version-specific API (IconSize vs.
 * Icon::SIZE_SMALL) this extension already has to work around elsewhere
 * (see ViewUtility.php). A single conditional item doesn't need that
 * machinery, and the <typo3-backend-icon> web component used below renders
 * identically on every supported TYPO3 major.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class RestoreFileProvider implements ProviderInterface
{
    private string $table = '';
    private string $identifier = '';

    public function __construct(
        private readonly FileResolver $fileResolver,
        private readonly FileRepository $fileRepository,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly UriBuilder $uriBuilder,
        private readonly FormProtectionFactory $formProtectionFactory,
    ) {}

    public function setContext(string $table, string $identifier, string $context = ''): void
    {
        $this->table = $table;
        $this->identifier = $identifier;
    }

    public function canHandle(): bool
    {
        return 'sys_file' === $this->table;
    }

    public function getPriority(): int
    {
        // Below TYPO3\CMS\Filelist\ContextMenu\ItemProviders\FileProvider's
        // fixed priority of 100: ContextMenu::getAvailableProviders() keys
        // its provider list by priority value, so an equal priority would
        // silently overwrite (not merge with) that entry instead of running
        // alongside it, wiping out the entire native file context menu. A
        // lower value also runs after it (higher priority runs first),
        // which is what places "Restore" after the core file items.
        return 40;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function addItems(array $items): array
    {
        // Backups (and therefore restore actions) are off by default. Short
        // circuit before resolving the file below, which would otherwise
        // run for every context-menu open on a file for a feature that
        // produces no restorable result in this configuration.
        if (!$this->extensionConfiguration->isBackupEnabled()) {
            return $items;
        }

        $file = $this->fileResolver->findFileByCombinedIdentifier($this->identifier);
        if (null === $file) {
            return $items;
        }

        $fileUid = $file->getUid();
        if ($fileUid <= 0 || null === $this->fileRepository->findBackupPathByUid($fileUid)) {
            return $items;
        }

        // The context menu's visibility is not an authorization boundary:
        // RestoreFileController re-checks this itself. Filtering here too
        // avoids offering an action that would just fail server-side.
        if (!$file->checkActionPermission('replace')) {
            return $items;
        }

        $formToken = $this->formProtectionFactory->createForType('backend')->generateToken(
            RestoreFileController::FORM_PROTECTION_FORM_NAME,
            RestoreFileController::FORM_PROTECTION_ACTION,
            (string) $fileUid,
        );

        $items['restore'] = [
            'type' => 'item',
            'label' => htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:typo3_image_compression/Resources/Private/Language/locallang.xlf:restoreAction'), \ENT_QUOTES),
            'icon' => '<typo3-backend-icon identifier="actions-delete-restore" size="small"></typo3-backend-icon>',
            'callbackAction' => 'restoreFile',
            'additionalAttributes' => [
                // No trailing .js: TYPO3's context-menu.js dispatcher appends
                // it itself before dynamically import()ing this module.
                'data-callback-module' => '@move-elevator/typo3-image-compression/RestoreFileContextMenuAction',
                'data-restorefileaction-url' => (string) $this->uriBuilder->buildUriFromRoute('tx_typo3imagecompression_restore'),
                'data-restorefileaction-uid' => (string) $fileUid,
                'data-restorefileaction-token' => $formToken,
            ],
        ];

        return $items;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
