<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Scss\Compilation;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ScssPhp\ScssPhp\OutputStyle;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use WapplerSystems\WsScss\Compiler as ScssCompiler;
use WapplerSystems\WsComponents\Scss\Registry\ScssComponentRegistry;

/**
 * Resolves a component key into SCSS files, builds a virtual entry SCSS,
 * compiles it via ws_scss (with its caching), and registers the resulting
 * CSS file with the AssetCollector.
 *
 * Idempotent per request — every key is processed at most once.
 *
 * Components may declare in the YAML which other component keys they already
 * embed via transitive @imports (registry `includes`). After the parent is
 * required, those embedded components are marked processed too, so a
 * subsequent standalone require call for them becomes a no-op and the same
 * CSS isn't emitted twice on the page.
 */
class ScssComponentCompiler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var array<string, true> */
    private array $processed = [];

    public function __construct(
        private readonly ScssComponentRegistry $registry,
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

        $files = $this->registry->getFiles($key);
        if ($files === []) {
            $this->logger?->debug('No SCSS files mapped for component key "{key}"', ['key' => $key]);
            return;
        }

        $imports = [...$this->registry->getSiteImports(), ...$files];

        $scss = '';
        foreach ($imports as $path) {
            $scss .= "@import '" . addcslashes($path, "'\\") . "';\n";
        }

        try {
            $cssFile = ScssCompiler::compileSassString(
                $scss,
                $this->registry->getSiteVariables(),
                null,
                false,
                OutputStyle::COMPRESSED,
            );
        } catch (\Throwable $e) {
            $this->logger?->error('SCSS component compilation failed for key "{key}": {msg}', [
                'key' => $key,
                'msg' => $e->getMessage(),
            ]);
            return;
        }

        if (!is_string($cssFile) || $cssFile === '') {
            return;
        }

        $absolutePath = GeneralUtility::getFileAbsFileName($cssFile);
        if (!is_file($absolutePath)) {
            $this->logger?->warning('SCSS compile returned a path that does not exist for key "{key}": {path}', [
                'key' => $key,
                'path' => $cssFile,
            ]);
            return;
        }

        $webPath = PathUtility::stripPathSitePrefix($absolutePath);

        $this->assetCollector->addStyleSheet(
            'ws-scss-component-' . $key,
            $webPath,
            ['media' => 'all'],
        );
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