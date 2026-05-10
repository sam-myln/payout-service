<?php declare(strict_types=1);

namespace Processing;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Processing\Contracts\ProcessorFactoryContract;
use Processing\Contracts\ProcessorRegistryContract;

final class ConfigDrivenProcessorRegistry implements ProcessorRegistryContract
{
    /**
     * @param list<string> $enabled
     * @param array<string, class-string<\Processing\Contracts\ProcessorFactoryContract>> $factories
     */
    public function __construct(
        private readonly array $enabled,
        private readonly array $factories,
        private readonly Container $container
    ) {
    }

    public function isEnabled(string $slug): bool
    {
        return in_array($slug, $this->enabled, true);
    }

    public function enabledSlugs(): array
    {
        return $this->enabled;
    }

    public function factoryFor(string $slug): ProcessorFactoryContract
    {
        if (!$this->isEnabled($slug)) {
            throw new InvalidArgumentException("Provider '{$slug}' is not enabled.");
        }

        $factoryClass = $this->factories[$slug] ?? null;
        if ($factoryClass === null) {
            throw new InvalidArgumentException("No factory registered for provider '{$slug}'.");
        }

        return $this->container->make($factoryClass);
    }
}
