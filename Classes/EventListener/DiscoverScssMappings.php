<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\EventListener;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\PackageManager;
use WapplerSystems\WsScssComponents\Event\RegisterScssComponentsEvent;
use WapplerSystems\WsScssComponents\Util\SitePackageActivation;

/**
 * Discovers Configuration/ScssComponents.yaml in every active package and
 * registers its mappings — but only when the package is active for the
 * currently rendered site (i.e. one of its Site Sets is included, or — for
 * packages without Site Sets — its composer name matches an active Set).
 *
 * Multiple main templates / site packages can coexist in one TYPO3 install
 * without leaking SCSS into each other's compiles.
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
        private readonly SitePackageActivation $sitePackageActivation,
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
            if (!$this->sitePackageActivation->isPackageActiveForCurrentRequest($package)) {
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