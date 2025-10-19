<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RecClick;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class DevicePreferenceService
{
    private const MAX_HISTORY = 250;

    private const HALF_LIFE_DAYS = 21.0;

    public function profileForDevice(string $deviceId): DevicePreferenceProfile
    {
        /** @var Collection<int, RecClick> $clicks */
        $clicks = RecClick::query()
            ->where('device_id', $deviceId)
            ->whereNotNull('movie_id')
            ->with(['movie:id,genres,type,year'])
            ->orderByDesc('created_at')
            ->limit(self::MAX_HISTORY)
            ->get();

        if ($clicks->isEmpty()) {
            return DevicePreferenceProfile::empty();
        }

        $genreWeights = [];
        $maxGenreWeight = 0.0;
        $typeWeights = [];
        $typeWeightSum = 0.0;
        $yearWeightedSum = 0.0;
        $yearSquaredWeightedSum = 0.0;
        $yearWeightTotal = 0.0;
        $samples = 0;

        $now = CarbonImmutable::now();

        foreach ($clicks as $click) {
            $movie = $click->movie;
            if ($movie === null) {
                continue;
            }

            $weight = $this->weightForTimestamp($click->created_at?->toImmutable(), $now);
            if ($weight <= 0.0) {
                continue;
            }

            $genres = $movie->genres;
            if (is_array($genres)) {
                foreach ($genres as $genre) {
                    if (! is_string($genre) || $genre === '') {
                        continue;
                    }

                    $key = mb_strtolower($genre);
                    $genreWeights[$key] = ($genreWeights[$key] ?? 0.0) + $weight;
                    $maxGenreWeight = max($maxGenreWeight, $genreWeights[$key]);
                }
            }

            $type = $movie->type;
            if (is_string($type) && $type !== '') {
                $typeKey = mb_strtolower($type);
                $typeWeights[$typeKey] = ($typeWeights[$typeKey] ?? 0.0) + $weight;
                $typeWeightSum += $weight;
            }

            $year = $movie->year;
            if (is_numeric($year)) {
                $yearValue = (float) $year;
                $yearWeightedSum += $yearValue * $weight;
                $yearSquaredWeightedSum += ($yearValue ** 2) * $weight;
                $yearWeightTotal += $weight;
            }

            $samples++;
        }

        if ($samples === 0) {
            return DevicePreferenceProfile::empty();
        }

        $preferredYear = null;
        $yearSpread = null;
        if ($yearWeightTotal > 0.0) {
            $preferredYear = $yearWeightedSum / $yearWeightTotal;
            $variance = ($yearSquaredWeightedSum / $yearWeightTotal) - ($preferredYear ** 2);
            if (is_finite($variance) && $variance > 0.0) {
                $yearSpread = sqrt($variance);
            }
        }

        return new DevicePreferenceProfile(
            $genreWeights,
            $maxGenreWeight,
            $typeWeights,
            $typeWeightSum,
            $preferredYear,
            $yearSpread,
            $samples,
        );
    }

    private function weightForTimestamp(?CarbonImmutable $timestamp, CarbonImmutable $now): float
    {
        if ($timestamp === null) {
            return 0.0;
        }

        $ageDays = $timestamp->diffInDays($now);
        if ($ageDays <= 0) {
            return 1.0;
        }

        $decay = pow(0.5, $ageDays / self::HALF_LIFE_DAYS);

        if (! is_finite($decay) || $decay <= 0.0) {
            return 0.0;
        }

        return $decay;
    }
}
