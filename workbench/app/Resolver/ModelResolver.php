<?php

namespace App\Resolver;

use Comhon\ModelResolverContract\ModelResolverInterface;

/**
 * A basic implementation of a model resolver
 */
class ModelResolver implements ModelResolverInterface
{
    public function __construct(private $bindings = [])
    {
        //
    }

    /**
     * Bind a unique name to a class.
     */
    public function bind(string $uniqueName, string $class): self
    {
        $this->bindings[$uniqueName] = $class;

        return $this;
    }

    /**
     * Get unique name according given class.
     */
    public function getUniqueName(string $class): ?string
    {
        return array_search($class, $this->bindings) ?: null;
    }

    /**
     * Get class according given unique name.
     */
    public function getClass(string $uniqueName): ?string
    {
        return $this->bindings[$uniqueName] ?? null;
    }
}
