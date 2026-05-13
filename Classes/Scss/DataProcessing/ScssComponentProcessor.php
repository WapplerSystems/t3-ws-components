<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Scss\DataProcessing;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use WapplerSystems\WsComponents\Scss\Compilation\ScssComponentCompiler;

/**
 * Requests SCSS components from inside TypoScript — useful when you cannot or
 * do not want to touch a Fluid template (e.g. third-party extensions).
 *
 * Example:
 *   tt_content.list.20.pagination_pi1 {
 *     dataProcessing.10 = WapplerSystems\WsComponents\Scss\DataProcessing\ScssComponentProcessor
 *     dataProcessing.10.keys = pagination,form
 *   }
 *
 * Only runs when the content element is actually rendered.
 */
class ScssComponentProcessor implements DataProcessorInterface
{
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $rawKeys = (string)$cObj->stdWrapValue('keys', $processorConfiguration, '');
        if ($rawKeys === '') {
            return $processedData;
        }

        $keys = array_values(array_filter(array_map('trim', explode(',', $rawKeys)), static fn($k) => $k !== ''));
        if ($keys === []) {
            return $processedData;
        }

        GeneralUtility::makeInstance(ScssComponentCompiler::class)->requireComponents($keys);

        $as = (string)($processorConfiguration['as'] ?? '');
        if ($as !== '') {
            $processedData[$as] = $keys;
        }

        return $processedData;
    }
}