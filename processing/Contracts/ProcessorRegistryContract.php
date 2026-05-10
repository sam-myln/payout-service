<?php declare(strict_types=1);

namespace Processing\Contracts;

interface ProcessorRegistryContract
{
    public function isEnabled(string $slug): bool;

    /** @return list<string> */
    public function enabledSlugs(): array;

    public function factoryFor(string $slug): ProcessorFactoryContract;
}
