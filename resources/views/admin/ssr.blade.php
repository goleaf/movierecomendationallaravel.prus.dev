@extends('layouts.app')

@section('title', __('admin.analytics_tabs.ssr.label'))

@section('content')
    <div class="grid" style="gap:18px">
        <div class="card" style="padding:18px">
            <h1 style="margin:0 0 12px;font-size:1.5rem">{{ __('admin.analytics_tabs.ssr.label') }}</h1>
            <p class="muted" style="margin-bottom:18px">{{ __('analytics.widgets.ssr_score.heading') }}</p>

            @include('filament.analytics.ssr-overview', ['summary' => $summary, 'trend' => $trend])
        </div>

        <div class="card" style="padding:18px">
            <h2 style="margin-top:0;margin-bottom:12px;font-size:1.25rem">{{ __('analytics.widgets.ssr_drop.heading') }}</h2>

            @if(count($issues) > 0)
                <ul class="muted" style="list-style:none;padding:0;margin:0">
                    @foreach($issues as $issue)
                        <li style="padding:12px 0;border-bottom:1px solid #1d2633">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                                <strong style="font-size:1rem;color:#f8fafc">{{ $issue['path'] }}</strong>
                                <span style="font-variant-numeric:tabular-nums">{{ number_format((float) $issue['avg_score'], 2) }}</span>
                            </div>

                            @if(! empty($issue['hints']))
                                <ul style="margin:8px 0 0 0;padding-left:18px">
                                    @foreach($issue['hints'] as $hint)
                                        <li>{{ $hint }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted">{{ __('analytics.widgets.ssr_drop.empty') }}</p>
            @endif
        </div>
    </div>
@endsection
