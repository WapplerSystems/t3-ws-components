<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Js\Loader;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\WsComponents\Js\Registry\JsComponentRegistry;

/**
 * Resolves a JS component key into one or more script files and registers them
 * with the AssetCollector. Idempotent per request — every key is processed at
 * most once.
 *
 * Components may declare in the YAML which other component keys they already
 * cover (registry `includes`). After the parent is required, those embedded
 * components are marked processed too, so a subsequent standalone require for
 * them becomes a no-op.
 */
class JsComponentLoader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var array<string, true> */
    private array $processed = [];

    public function __construct(
        private readonly JsComponentRegistry $registry,
        private readonly AssetCollector $assetCollector,
    ) {}

    public function requireComponent(string $key): void
    {
        if ($key === '' || isset($this->processed[$key])) {
            return;
        }
        $this->processed[$key] = true;

        foreach ($this->registry->getIncludes($key) as $includedKey) {
            $this->processed[$includedKey] = true;
        }

        $entries = $this->registry->getEntries($key);
        if ($entries === []) {
            $this->logger?->debug('No JS files mapped for component key "{key}"', ['key' => $key]);
            return;
        }

        foreach ($entries as $index => $entry) {
            $file = $entry['file'];
            $absolute = GeneralUtility::getFileAbsFileName($file);
            if (!is_file($absolute)) {
                $this->logger?->warning('JS component file does not exist for key "{key}": {path}', [
                    'key' => $key,
                    'path' => $file,
                ]);
                continue;
            }
            $webPath = str_starts_with($file, 'EXT:') ? $file : PathUtility::stripPathSitePrefix($absolute);

            $identifier = 'ws-js-component-' . $key . (count($entries) > 1 ? '-' . $index : '');
            $this->assetCollector->addJavaScript(
                $identifier,
                $webPath,
                $entry['attributes'] ?? [],
            );
        }
    }

    /**
     * @param string[] $keys
     */
    public function requireComponents(array $keys): void
    {
        foreach ($keys as $key) {
            $this->requireComponent((string)$key);
        }
    }
}
