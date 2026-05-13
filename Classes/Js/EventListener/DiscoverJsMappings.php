<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Js\EventListener;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\PackageManager;
use WapplerSystems\WsComponents\Js\Event\RegisterJsComponentsEvent;
use WapplerSystems\WsComponents\Js\Registry\JsComponentRegistry;
use WapplerSystems\WsComponents\Util\SitePackageActivation;

/**
 * Discovers Configuration/JsComponents.yaml in every active package and
 * registers its mappings — but only when the package is active for the
 * currently rendered site.
 */
final class DiscoverJsMappings
{
    private const YAML_FILE = 'Configuration/JsComponents.yaml';

    public function __construct(
        private readonly PackageManager $packageManager,
        private readonly SitePackageActivation $sitePackageActivation,
    ) {}

    #[AsEventListener('ws-components/discover-js-mappings')]
    public function __invoke(RegisterJsComponentsEvent $event): void
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
    }

    private function applyYaml(JsComponentRegistry $registry, string $yamlPath): void
    {
        $config = (array)(Yaml::parseFile($yamlPath) ?? []);

        foreach ((array)($config['components'] ?? []) as $key => $definition) {
            if (!is_string($key) || $key === '' || !is_array($definition)) {
                continue;
            }
            [$entries, $includes] = $this->normalizeComponentDefinition($definition);
            if ($entries === []) {
                continue;
            }
            $registry->register($key, $entries, $includes);
        }
    }

    /**
     * Accepts either the flat list form
     *   key:
     *     - 'EXT:foo/Resources/Public/JavaScript/a.js'
     *     - 'EXT:foo/Resources/Public/JavaScript/b.js'
     * or the map form
     *   key:
     *     files:
     *       - 'EXT:foo/Resources/Public/JavaScript/a.js'
     *       - file: 'EXT:foo/Resources/Public/JavaScript/b.js'
     *         attributes:
     *           defer: true
     *           type: module
     *     includes:
     *       - jquery
     *
     * @param array<int|string, mixed> $definition
     * @return array{0: list<array{file: string, attributes: array<string, mixed>}>, 1: string[]}
     */
    private function normalizeComponentDefinition(array $definition): array
    {
        $isList = array_is_list($definition);
        if ($isList) {
            $rawFiles = $definition;
            $includes = [];
        } else {
            $rawFiles = (array)($definition['files'] ?? []);
            $includes = (array)($definition['includes'] ?? []);
        }

        $entries = [];
        foreach ($rawFiles as $rawFile) {
            if (is_string($rawFile)) {
                if ($rawFile === '') {
                    continue;
                }
                $entries[] = ['file' => $rawFile, 'attributes' => []];
                continue;
            }
            if (is_array($rawFile)) {
                $file = (string)($rawFile['file'] ?? '');
                if ($file === '') {
                    continue;
                }
                $entries[] = [
                    'file' => $file,
                    'attributes' => (array)($rawFile['attributes'] ?? []),
                ];
            }
        }

        return [
            $entries,
            array_values(array_filter(array_map('strval', $includes), static fn($v) => $v !== '')),
        ];
    }
}
