<?php

declare(strict_types=1);

namespace App\Attributes;

use ReflectionAttribute;
use ReflectionClass;

final class ComponentMetadata
{
    /**
     * @param  array<int, string>  $policies
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?CacheMetadata $cache,
        public readonly array $policies,
    ) {}

    public static function for(object|string $component): self
    {
        $className = is_object($component) ? $component::class : $component;
        $builder = new ComponentMetadataBuilder;

        foreach (self::resolveHierarchy($className) as $reflection) {
            foreach ($reflection->getAttributes(ComponentAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var ComponentAttribute $instance */
                $instance = $attribute->newInstance();
                $instance->apply($builder);
            }
        }

        return $builder->build();
    }

    /**
     * @return iterable<ReflectionClass<object>>
     */
    private static function resolveHierarchy(string $className): iterable
    {
        $reflection = new ReflectionClass($className);
        $hierarchy = [];

        do {
            $hierarchy[] = $reflection;
            $reflection = $reflection->getParentClass();
        } while ($reflection instanceof ReflectionClass);

        return array_reverse($hierarchy);
    }
}
