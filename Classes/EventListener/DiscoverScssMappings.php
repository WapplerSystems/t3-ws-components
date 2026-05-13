<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\EventListener;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\PackageManager;
use WapplerSystems\WsScssComponents\Event\RegisterScssComponentsEvent;
use WapplerSystems\WsScssComponents\Util\SiteSetActivation;

/**
 * Discovers Configuration/ScssComponents.yaml in every active extension and
 * registers its mappings — but only when the extension's Site Set is active
 * for the currently rendered site.
 *
 * Convention: the Site Set name equals the extension's composer package name
 * (e.g. composer "t3bootstrap/template" maps to Site Set "t3bootstrap/template").
 * Extensions that don't ship a matching Site Set, or whose Set isn't active
 * for the current site, are silently skipped — so multiple main templates can
 * coexist in one TYPO3 install without leaking SCSS into each other's compiles.
 *
 * Additionally forwards plugin.tx_wsscss.variables.* from the current frontend
 * TypoScript setup as site variables — those are genuinely site-wide and not
 * tied to a specific template.
 */
final class DiscoverScssMappings
{
    private const YAML_FILE = 'Configuration/ScssComponents.yaml';

    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly SiteSetActivation $siteSetActivation,
    ) {}

    #[AsEventListener('ws-scss-components/discover-scss-mappings')]
    public function __invoke(RegisterScssComponentsEvent $event): void
    {
        $registry = $event->getRegistry();

        foreach ($this->packageManager->getActivePackages() as $package) {
            $yamlPath = $package->getPackagePath() . self::YAML_FILE;
            if (!is_file($yamlPath)) {
                continue;
            }

            $composerName = $package->getValueFromComposerManifest('name');
            if (!is_string($composerName) || $composerName === '') {
                continue;
            }
            if (!$this->siteSetActivation->isSetActiveForCurrentRequest($composerName)) {
                continue;
            }

            $this->applyYaml($registry, $yamlPath);
        }

        $variables = $this->collectScssVariablesFromTypoScript();
        if ($variables !== []) {
            $registry->addSiteVariables($variables);
        }
    }

    private function applyYaml(\WapplerSystems\WsScssComponents\Registry\ScssComponentRegistry $registry, string $yamlPath): void
    {
        $config = (array)(Yaml::parseFile($yamlPath) ?? []);

        $siteImports = (array)($config['siteImports'] ?? []);
        if ($siteImports !== []) {
            $registry->addSiteImports(array_values(array_filter(array_map('strval', $siteImports))));
        }

        foreach ((array)($config['components'] ?? []) as $key => $files) {
            if (!is_string($key) || $key === '' || !is_array($files)) {
                continue;
            }
            $registry->register($key, array_values(array_filter(array_map('strval', $files))));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collectScssVariablesFromTypoScript(): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return [];
        }

        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if ($frontendTypoScript === null) {
            return [];
        }

        $setup = $frontendTypoScript->getSetupArray();
        $raw = $setup['plugin.']['tx_wsscss.']['variables.'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $name => $value) {
            $cleanName = rtrim((string)$name, '.');
            if ($cleanName === '' || is_array($value)) {
                continue;
            }
            $result[$cleanName] = (string)$value;
        }
        return $result;
    }
}