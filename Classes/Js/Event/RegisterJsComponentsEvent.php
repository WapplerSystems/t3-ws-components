<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Js\Event;

use WapplerSystems\WsComponents\Js\Registry\JsComponentRegistry;

/**
 * Dispatched once per request when the JS component registry is first read.
 * Listeners register their key → JS file mappings.
 */
final class RegisterJsComponentsEvent
{
    public function __construct(
        private readonly JsComponentRegistry $registry,
    ) {}

    public function getRegistry(): JsComponentRegistry
    {
        return $this->registry;
    }
}
