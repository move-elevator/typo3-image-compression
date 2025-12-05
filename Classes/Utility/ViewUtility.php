<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_image_compression" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 * (c) 2025 Ronny Hauptvogel <rh@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3ImageCompression\Utility;

use MoveElevator\Typo3ImageCompression\Configuration;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\{ViewFactoryData, ViewFactoryInterface};

/**
 * ViewUtility.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @author Ronny Hauptvogel <rh@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class ViewUtility
{
    /**
     * Renders a template with the given variables.
     *
     * @param string               $templateName Template name without extension (e.g., 'CompressionStatistics')
     * @param string               $templatePath Template path relative to extension (e.g., 'Report/')
     * @param array<string, mixed> $variables    Variables to assign to the view
     */
    public static function renderTemplate(string $templateName, string $templatePath, array $variables): string
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        if ($typo3Version >= 13) {
            return self::renderTemplateV13($templateName, $templatePath, $variables);
        }

        return self::renderTemplateV12($templateName, $templatePath, $variables);
    }

    /**
     * Render template using ViewFactoryInterface (TYPO3 v13+).
     *
     * @param array<string, mixed> $variables
     */
    private static function renderTemplateV13(string $templateName, string $templatePath, array $variables): string
    {
        $viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:'.Configuration::EXT_KEY.'/Resources/Private/Templates/'.$templatePath],
        );
        $view = $viewFactory->create($viewFactoryData);
        $view->assignMultiple($variables);

        return $view->render($templateName);
    }

    /**
     * Render template using StandaloneView (TYPO3 v12).
     *
     * @param array<string, mixed> $variables
     */
    private static function renderTemplateV12(string $templateName, string $templatePath, array $variables): string
    {
        // @phpstan-ignore-next-line StandaloneView is deprecated in v13 but required for v12
        $view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
        $view->setTemplatePathAndFilename(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Private/Templates/'.$templatePath.$templateName.'.html',
        );
        $view->assignMultiple($variables);

        return $view->render();
    }
}
