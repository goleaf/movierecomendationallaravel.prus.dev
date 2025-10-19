<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('rec_weights.variant_a_pop', (float) env('REC_A_POP', 0.55));
        $this->migrator->add('rec_weights.variant_a_recent', (float) env('REC_A_RECENT', 0.20));
        $this->migrator->add('rec_weights.variant_a_pref', (float) env('REC_A_PREF', 0.25));

        $this->migrator->add('rec_weights.variant_b_pop', (float) env('REC_B_POP', 0.35));
        $this->migrator->add('rec_weights.variant_b_recent', (float) env('REC_B_RECENT', 0.15));
        $this->migrator->add('rec_weights.variant_b_pref', (float) env('REC_B_PREF', 0.50));
    }
};
