<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Js\Registry;

use Psr\EventDispatcher\EventDispatcherInterface;
use WapplerSystems\WsComponents\Js\Event\RegisterJsComponentsEvent;

/**
 * Holds the key → JS file(s) mapping along with per-component attributes
 * (defer/async/type/priority/...) and optional "includes" deps for dedup.
 *
 * Populated per request by the listeners of the site's active main template
 * or site packages — what's registered here applies only to the currently
 * rendered site, so multiple templates can coexist without interfering.
 *
 * The first read access dispatches RegisterJsComponentsEvent so listeners
 * can register their mappings lazily.
 */
class JsComponentRegistry
{
    /**
     * @var array<string, list<array{file: string, attributes: array<string, mixed>}>>
     */
    private array $componentMap = [];

    /** @var array<string, string[]> */
    private array $componentIncludes = [];

    private bool $initialized = false;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param list<array{file: string, attributes?: array<string, mixed>}> $entries
     *        File entries with optional per-entry attributes (e.g. defer, async,
     *        type, priority).
     * @param string[] $includes Component keys whose JS this component already
     *                           covers, so subsequent standalone requires for
     *                           them after this one are no-ops.
     */
    public function register(string $key, array $entries, array $includes = []): void
    {
        $normalized = [];
        foreach ($entries as $entry) {
            $file = (string)($entry['file'] ?? '');
            if ($file === '') {
                continue;
            }
            $normalized[] = [
                'file' => $file,
                'attributes' => (array)($entry['attributes'] ?? []),
            ];
        }
        $existing = $this->componentMap[$key] ?? [];
        $this->componentMap[$key] = array_merge($existing, $normalized);

        if ($includes !== []) {
            $existingIncludes = $this->componentIncludes[$key] ?? [];
            $this->componentIncludes[$key] = array_values(array_unique([...$existingIncludes, ...$includes]));
        }
    }

    /**
     * @return list<array{file: string, attributes: array<string, mixed>}>
     */
    public function getEntries(string $key): array
    {
        $this->initialize();
        return $this->componentMap[$key] ?? [];
    }

    /** @return string[] */
    public function getIncludes(string $key): array
    {
        $this->initialize();
        return $this->componentIncludes[$key] ?? [];
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
        $this->eventDispatcher->dispatch(new RegisterJsComponentsEvent($this));
    }
}
