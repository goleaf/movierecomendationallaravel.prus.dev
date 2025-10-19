<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\RecommendationWeightsPage;
use App\Models\User;
use App\Settings\RecommendationWeightsSettings;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithRecommendationWeightsSettings;
use Tests\TestCase;

class RecommendationWeightsPageTest extends TestCase
{
    use InteractsWithRecommendationWeightsSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('analytics'));
        session()->forget('filament.notifications');
    }

    public function test_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Filament::auth()->login($user);

        Livewire::test(RecommendationWeightsPage::class)
            ->assertOk()
            ->assertSee('Save weights');
    }

    public function test_validation_rejects_negative_weights(): void
    {
        $user = User::factory()->create();
        Filament::auth()->login($user);

        $initial = app(RecommendationWeightsSettings::class)->variant_a;

        Livewire::test(RecommendationWeightsPage::class)
            ->set('data.variant_a.pop', -0.2)
            ->call('submit')
            ->assertHasErrors(['data.variant_a.pop' => ['min']]);

        Notification::assertNotNotified('Recommendation weights updated');
        $this->assertSame($initial, app(RecommendationWeightsSettings::class)->variant_a);
    }

    public function test_saving_weights_updates_settings_and_sends_notification(): void
    {
        $user = User::factory()->create();
        Filament::auth()->login($user);

        $this->updateRecommendationWeightsSettings([
            'variant_a' => ['pop' => 0.5, 'recent' => 0.3, 'pref' => 0.2],
            'variant_b' => ['pop' => 0.4, 'recent' => 0.4, 'pref' => 0.2],
            'ab_split' => ['A' => 55.0, 'B' => 45.0],
            'seed' => 'initial',
        ]);

        $component = Livewire::test(RecommendationWeightsPage::class)
            ->set('data.variant_a.pop', 0.8)
            ->set('data.variant_a.recent', 0.1)
            ->set('data.variant_a.pref', 0.1)
            ->set('data.variant_b.pop', 0.25)
            ->set('data.variant_b.recent', 0.5)
            ->set('data.variant_b.pref', 0.25)
            ->set('data.ab_split.A', 35)
            ->set('data.ab_split.B', 65)
            ->set('data.seed', 'integration-test');

        $state = $component->get('data');

        $this->assertSame(0.8, (float) ($state['variant_a']['pop'] ?? -1.0));

        $component
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertNotified('Recommendation weights updated');

        app()->forgetInstance(RecommendationWeightsSettings::class);
        $settings = app(RecommendationWeightsSettings::class);

        $this->assertSame([
            'pop' => 0.8,
            'recent' => 0.1,
            'pref' => 0.1,
        ], $settings->variant_a);
        $this->assertSame([
            'pop' => 0.25,
            'recent' => 0.5,
            'pref' => 0.25,
        ], $settings->variant_b);
        $this->assertSame([
            'A' => 35.0,
            'B' => 65.0,
        ], array_map('floatval', $settings->ab_split));
        $this->assertSame('integration-test', $settings->seed);
    }
}
