<?php
declare(strict_types=1);

namespace WapplerSystems\WsScssComponents\Registry;

use Psr\EventDispatcher\EventDispatcherInterface;
use WapplerSystems\WsScssComponents\Event\RegisterScssComponentsEvent;

/**
 * Holds the key → SCSS files mapping plus site-wide imports / variables.
 *
 * Imports and variables are populated per request by the listeners of the
 * site's active main template (see SiteSetActivation). Hence "site" scope:
 * what's registered here applies to every component compile of the currently
 * rendered site, but not across sites with different main templates.
 *
 * The first read access dispatches RegisterScssComponentsEvent so listeners
 * can register their mappings lazily.
 */
class ScssComponentRegistry
{
    /** @var array<string, string[]> */
    private array $componentMap = [];

    /** @var string[] */
    private array $siteImports = [];

    /** @var array<string, mixed> */
    private array $siteVariables = [];

    private bool $initialized = false;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param string[] $files Paths (EXT:… or absolute web paths) to SCSS files
     */
    public function register(string $key, array $files): void
    {
        $existing = $this->componentMap[$key] ?? [];
        $this->componentMap[$key] = array_values(array_unique([...$existing, ...$files]));
    }

    /**
     * @param string[] $imports Paths included before any component file (e.g. _variables.scss)
     */
    public function addSiteImports(array $imports): void
    {
        $this->siteImports = array_values(array_unique([...$this->siteImports, ...$imports]));
    }

    /**
     * @param array<string, mixed> $variables SCSS variables forwarded to the compiler
     */
    public function addSiteVariables(array $variables): void
    {
        $this->siteVariables = array_replace($this->siteVariables, $variables);
    }

    /** @return string[] */
    public function getFiles(string $key): array
    {
        $this->initialize();
        return $this->componentMap[$key] ?? [];
    }

    /** @return string[] */
    public function getSiteImports(): array
    {
        $this->initialize();
        return $this->siteImports;
    }

    /** @return array<string, mixed> */
    public function getSiteVariables(): array
    {
        $this->initialize();
        return $this->siteVariables;
    }

    public function hasComponent(string $key): bool
    {
        $this->initialize();
        return isset($this->componentMap[$key]);
    }

    /** @return string[] */
    public function getRegisteredKeys(): array
    {
        $this->initialize();
        return array_keys($this->componentMap);
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        $this->eventDispatcher->dispatch(new RegisterScssComponentsEvent($this));
    }
}