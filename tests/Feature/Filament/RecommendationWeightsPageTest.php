<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\RecommendationWeightsPage;
use App\Models\User;
use App\Settings\RecommendationWeightsSettings;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Tests\TestCase;

final class RecommendationWeightsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
        }

        config(['app.env' => 'local']);
    }

    public function test_page_renders_current_weights(): void
    {
        $this->storeRecommendationWeights([
            'A' => ['pop' => 0.65, 'recent' => 0.25, 'pref' => 0.10],
            'ab_split' => ['A' => 55.0, 'B' => 45.0],
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/analytics/recommendation-weights');

        $response->assertOk();
        $response->assertSeeText(__('admin.recommendation_weights.sections.variant_a'));
        $response->assertSee('0.65');
        $response->assertSee('55.00');
        $response->assertSeeText(__('admin.recommendation_weights.summary.heading'));
    }

    public function test_saving_updates_settings_and_notifies(): void
    {
        session()->forget('filament.notifications');

        $user = User::factory()->create();
        $this->actingAs($user);

        $component = Livewire::test(RecommendationWeightsPage::class)
            ->set('data.A.pop', '0.75')
            ->set('data.A.recent', '0.15')
            ->set('data.A.pref', '0.10')
            ->set('data.B.pop', '0.25')
            ->set('data.B.recent', '0.35')
            ->set('data.B.pref', '0.40')
            ->set('data.ab_split.A', '55')
            ->set('data.ab_split.B', '45')
            ->set('data.seed', 'feature-test')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('data.ab_split.A', '55.00')
            ->assertSet('data.seed', 'feature-test');

        Notification::assertNotified(__('admin.recommendation_weights.actions.saved'));

        /** @var SettingsRepository $repository */
        $repository = app(SettingsRepository::class);
        $stored = $repository->getPropertiesInGroup(RecommendationWeightsSettings::group());

        $this->assertEquals(0.75, $stored['A']['pop']);
        $this->assertEquals(0.15, $stored['A']['recent']);
        $this->assertEquals(0.10, $stored['A']['pref']);
        $this->assertEquals(55.0, $stored['ab_split']['A']);
        $this->assertEquals(45.0, $stored['ab_split']['B']);
        $this->assertSame('feature-test', $stored['seed']);

        $component->assertSet('normalised.A.pop', 0.75);
    }
}
