<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\Util;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Package\PackageInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Set\SetRegistry;

/**
 * Decides whether a TYPO3 site package contributes to the currently rendered
 * site. A package is considered active when any of the Site Sets it ships
 * (Configuration/Sets/<Name>/config.yaml) is active on the site — directly or
 * transitively. Packages that don't ship Site Sets fall back to a match on
 * their composer package name.
 *
 * Used by SCSS component discovery so multiple main templates / site packages
 * can coexist in one TYPO3 install without leaking their mappings.
 */
final class SitePackageActivation
{
    public function __construct(
        private readonly SetRegistry $setRegistry,
    ) {}

    public function isPackageActiveForCurrentRequest(PackageInterface $package): bool
    {
        $site = $this->resolveSiteFromCurrentRequest();
        return $site !== null && $this->isPackageActiveForSite($package, $site);
    }

    public function isPackageActiveForSite(PackageInterface $package, Site $site): bool
    {
        $candidateSets = $this->collectPackageSetNames($package);
        if ($candidateSets === []) {
            $composerName = (string)($package->getValueFromComposerManifest('name') ?? '');
            if ($composerName === '') {
                return false;
            }
            $candidateSets = [$composerName];
        }

        $direct = $site->getSets();
        foreach ($candidateSets as $name) {
            if (in_array($name, $direct, true)) {
                return true;
            }
        }
        foreach ($this->setRegistry->getSets(...$direct) as $resolved) {
            if (in_array($resolved->name, $candidateSets, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    private function collectPackageSetNames(PackageInterface $package): array
    {
        $setsDir = $package->getPackagePath() . 'Configuration/Sets/';
        if (!is_dir($setsDir)) {
            return [];
        }
        $names = [];
        foreach (glob($setsDir . '*/config.yaml') ?: [] as $configFile) {
            $config = Yaml::parseFile($configFile);
            $name = is_array($config) ? ($config['name'] ?? null) : null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }

    private function resolveSiteFromCurrentRequest(): ?Site
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }
        $site = $request->getAttribute('site');
        return $site instanceof Site ? $site : null;
    }
}