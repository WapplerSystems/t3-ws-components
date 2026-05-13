<?php
declare(strict_types=1);

namespace WapplerSystems\WsComponents\Scss\Event;

use WapplerSystems\WsComponents\Scss\Registry\ScssComponentRegistry;

/**
 * Dispatched once per request when the registry is first read.
 * Listeners register their key → file mappings, global imports and variables.
 */
final class RegisterScssComponentsEvent
{
    public function __construct(
        private readonly ScssComponentRegistry $registry,
    ) {}

    public function getRegistry(): ScssComponentRegistry
    {
        return $this->registry;
    }
}