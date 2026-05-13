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

        foreach ((array)($config['components'] ?? []) as $key => $definition) {
            if (!is_string($key) || $key === '' || !is_array($definition)) {
                continue;
            }
            [$files, $includes] = $this->normalizeComponentDefinition($definition);
            if ($files === []) {
                continue;
            }
            $registry->register($key, $files, $includes);
        }
    }

    /**
     * Accepts either the flat list form
     *   key:
     *     - 'EXT:foo/Resources/.../a.scss'
     *     - 'EXT:foo/Resources/.../b.scss'
     * or the map form
     *   key:
     *     files:
     *       - 'EXT:foo/Resources/.../a.scss'
     *     includes:
     *       - pagination
     *
     * @param array<int|string, mixed> $definition
     * @return array{0: string[], 1: string[]}
     */
    private function normalizeComponentDefinition(array $definition): array
    {
        $isList = array_is_list($definition);
        if ($isList) {
            $files = $definition;
            $includes = [];
        } else {
            $files = (array)($definition['files'] ?? []);
            $includes = (array)($definition['includes'] ?? []);
        }
        return [
            array_values(array_filter(array_map('strval', $files), static fn($v) => $v !== '')),
            array_values(array_filter(array_map('strval', $includes), static fn($v) => $v !== '')),
        ];
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
            $stringValue = (string)$value;
            // Skip empty strings — they would otherwise be injected as
            // empty SCSS values and break functions like shade-color() in
            // Bootstrap when a site setting is unset.
            if ($stringValue === '') {
                continue;
            }
            $result[$cleanName] = $stringValue;
        }
        return $result;
    }
}