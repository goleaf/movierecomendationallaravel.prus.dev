<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Attributes\CacheMetadata;
use App\Attributes\ComponentMetadata;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttributesTest extends TestCase
{
    #[Test]
    public function it_reads_component_metadata_from_attributes(): void
    {
        $metadata = ComponentMetadata::for(Fixtures\ChildComponent::class);

        $this->assertSame('Base Title', $metadata->title);
        $this->assertInstanceOf(CacheMetadata::class, $metadata->cache);
        $this->assertSame('base-cache', $metadata->cache?->key);
        $this->assertSame(600, $metadata->cache?->ttl);
        $this->assertSame(['analytics'], $metadata->cache?->tags);
        $this->assertSame(['viewBase', 'manageBase'], $metadata->policies);
    }

    #[Test]
    public function it_allows_children_to_override_parent_metadata(): void
    {
        $metadata = ComponentMetadata::for(Fixtures\OverrideComponent::class);

        $this->assertSame('Override Title', $metadata->title);
        $this->assertInstanceOf(CacheMetadata::class, $metadata->cache);
        $this->assertSame('override-cache', $metadata->cache?->key);
        $this->assertSame(120, $metadata->cache?->ttl);
        $this->assertSame(['analytics', 'queue'], $metadata->cache?->tags);
        $this->assertSame(['viewBase', 'manageBase', 'viewOverride'], $metadata->policies);
    }

    #[Test]
    public function it_merges_layout_data_with_attribute_title(): void
    {
        $metadata = ComponentMetadata::for(Fixtures\InlineComponent::class);
        $this->assertSame('Inline Title', $metadata->title);

        $layout = (new Fixtures\InlineComponent)->exposedLayoutData([
            'metaDescription' => 'Example',
        ]);

        $this->assertSame([
            'title' => 'Inline Title',
            'metaDescription' => 'Example',
        ], $layout);
    }
}

namespace Tests\Unit\Fixtures;

use App\Attributes\Cache;
use App\Attributes\Concerns\InteractsWithComponentAttributes;
use App\Attributes\Policies;
use App\Attributes\Title;
use Livewire\Component;

#[Title('Base Title')]
#[Cache('base-cache', ttl: 600, tags: ['analytics'])]
#[Policies('viewBase', 'manageBase')]
class BaseComponent extends Component
{
    use InteractsWithComponentAttributes;
}

class ChildComponent extends BaseComponent {}

#[Title('Override Title')]
#[Cache('override-cache', ttl: 120, tags: ['analytics', 'queue'])]
#[Policies('viewOverride')]
class OverrideComponent extends BaseComponent {}

#[Title('Inline Title')]
class InlineComponent extends Component
{
    use InteractsWithComponentAttributes;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function exposedLayoutData(array $overrides = []): array
    {
        return $this->layoutData($overrides);
    }
}
