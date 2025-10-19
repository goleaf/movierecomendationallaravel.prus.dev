<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\Administration\RecommendationWeightsPage;
use App\Settings\RecommendationWeightsSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RecommendationWeightsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('analytics'));
        Filament::bootCurrentPanel();
        Filament::setServingStatus();
    }

    public function test_defaults_are_seeded_from_configuration(): void
    {
        config()->set('recs.A.pop', 0.61);
        config()->set('recs.A.recent', 0.24);
        config()->set('recs.A.pref', 0.15);
        config()->set('recs.B.pop', 0.31);
        config()->set('recs.B.recent', 0.19);
        config()->set('recs.B.pref', 0.50);
        config()->set('recs.ab_split.A', 65.0);
        config()->set('recs.ab_split.B', 35.0);

        DB::table('settings')->where('group', 'recommendation-weights')->delete();
        app()->forgetInstance(RecommendationWeightsSettings::class);

        $defaults = RecommendationWeightsSettings::defaults();

        $this->assertSame(0.61, $defaults['variant_a_pop']);
        $this->assertSame(0.24, $defaults['variant_a_recent']);
        $this->assertSame(0.15, $defaults['variant_a_pref']);
        $this->assertSame(0.31, $defaults['variant_b_pop']);
        $this->assertSame(0.19, $defaults['variant_b_recent']);
        $this->assertSame(0.50, $defaults['variant_b_pref']);
        $this->assertSame(65.0, $defaults['ab_split_a']);
        $this->assertSame(35.0, $defaults['ab_split_b']);

        $settings = new RecommendationWeightsSettings($defaults);

        $this->assertSame(0.61, $settings->variant_a_pop);
        $this->assertSame(0.24, $settings->variant_a_recent);
        $this->assertSame(0.15, $settings->variant_a_pref);
        $this->assertSame(0.31, $settings->variant_b_pop);
        $this->assertSame(0.19, $settings->variant_b_recent);
        $this->assertSame(0.50, $settings->variant_b_pref);
        $this->assertSame(65.0, $settings->ab_split_a);
        $this->assertSame(35.0, $settings->ab_split_b);
    }

    public function test_form_updates_persist_settings_and_refresh_instance(): void
    {
        $component = Livewire::test(RecommendationWeightsPage::class);

        $component
            ->set('formData.variant_a_pop', 0.5)
            ->set('formData.variant_a_recent', 0.3)
            ->set('formData.variant_a_pref', 0.2)
            ->set('formData.variant_b_pop', 0.4)
            ->set('formData.variant_b_recent', 0.4)
            ->set('formData.variant_b_pref', 0.2)
            ->set('formData.ab_split_a', 55.0)
            ->set('formData.ab_split_b', 45.0)
            ->call('save')
            ->assertHasNoErrors();

        $settings = app(RecommendationWeightsSettings::class);

        $this->assertSame(0.5, $settings->variant_a_pop);
        $this->assertSame(0.3, $settings->variant_a_recent);
        $this->assertSame(0.2, $settings->variant_a_pref);
        $this->assertSame(0.4, $settings->variant_b_pop);
        $this->assertSame(0.4, $settings->variant_b_recent);
        $this->assertSame(0.2, $settings->variant_b_pref);
        $this->assertSame(55.0, $settings->ab_split_a);
        $this->assertSame(45.0, $settings->ab_split_b);
    }

    public function test_validation_enforces_weight_totals(): void
    {
        Livewire::test(RecommendationWeightsPage::class)
            ->set('formData.variant_a_pop', 0.9)
            ->set('formData.variant_a_recent', 0.05)
            ->set('formData.variant_a_pref', 0.05)
            ->set('formData.variant_b_pop', 0.5)
            ->set('formData.variant_b_recent', 0.5)
            ->set('formData.variant_b_pref', 0.2)
            ->set('formData.ab_split_a', 0)
            ->set('formData.ab_split_b', 0)
            ->call('save')
            ->assertHasErrors([
                'variant_b_pop' => null,
                'ab_split_a' => null,
            ]);
    }
}
