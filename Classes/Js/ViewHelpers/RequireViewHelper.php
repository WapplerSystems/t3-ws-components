<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Js\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WapplerSystems\WsComponents\Js\Loader\JsComponentLoader;

/**
 * Marks a JS component as required by the surrounding template. The active
 * package's mapping decides which file(s) the key resolves to and which
 * attributes (defer/async/type) are applied.
 *
 * Usage:
 *   <jsComponents:require key="glightbox" />
 *
 * The "jsComponents" Fluid namespace is registered globally by this
 * extension's ext_localconf.php, so no xmlns declaration is required.
 *
 * Only enqueues when the surrounding template is actually rendered.
 */
final class RequireViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;
    protected $escapeChildren = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Component key registered by the active package', true);
    }

    public function render(): string
    {
        $key = (string)$this->arguments['key'];
        GeneralUtility::makeInstance(JsComponentLoader::class)->requireComponent($key);
        return '';
    }

    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ): string {
        GeneralUtility::makeInstance(JsComponentLoader::class)
            ->requireComponent((string)$arguments['key']);
        return '';
    }
}
