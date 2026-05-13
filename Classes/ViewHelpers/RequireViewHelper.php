<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\WsScssComponents\Compilation\ScssComponentCompiler;

/**
 * Marks a SCSS component as required by the surrounding template. The active
 * main template's mapping decides which SCSS file(s) the key resolves to.
 *
 * Usage:
 *   <scssComponents:require key="form" />
 *
 * The "scssComponents" Fluid namespace is registered globally by this
 * extension's ext_localconf.php, so no xmlns declaration is required.
 *
 * Only emits CSS when the surrounding template is actually rendered.
 */
final class RequireViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Component key registered by the main template', true);
    }

    public function render(): string
    {
        $key = (string)$this->arguments['key'];
        GeneralUtility::makeInstance(ScssComponentCompiler::class)->requireComponent($key);
        return '';
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ): string {
        GeneralUtility::makeInstance(ScssComponentCompiler::class)
            ->requireComponent((string)$arguments['key']);
        return '';
    }
}