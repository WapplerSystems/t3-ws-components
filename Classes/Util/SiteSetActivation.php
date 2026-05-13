<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\Util;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Set\SetRegistry;

/**
 * Helper for SCSS mapping listeners. Decides whether a given Site Set is active
 * for the currently rendered site, including transitive dependencies.
 *
 * Listeners that contribute SCSS mappings on behalf of a main template should
 * short-circuit when this returns false so their mappings don't leak into
 * requests served by a different main template installed in the same system.
 */
final class SiteSetActivation
{
    public function __construct(
        private readonly SetRegistry $setRegistry,
    ) {}

    public function isSetActiveForCurrentRequest(string $setName): bool
    {
        $site = $this->resolveSiteFromCurrentRequest();
        return $site !== null && $this->isSetActiveForSite($setName, $site);
    }

    public function isSetActiveForSite(string $setName, Site $site): bool
    {
        $direct = $site->getSets();
        if (in_array($setName, $direct, true)) {
            return true;
        }
        foreach ($this->setRegistry->getSets(...$direct) as $resolved) {
            if ($resolved->name === $setName) {
                return true;
            }
        }
        return false;
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