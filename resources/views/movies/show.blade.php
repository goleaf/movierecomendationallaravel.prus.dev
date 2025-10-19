@extends('layouts.app')
@section('title', $movie->title)
@section('content')
<div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
    <div class="grid gap-6 md:grid-cols-[220px_1fr]">
        @if($movie->poster_url)
            <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" class="w-full rounded-xl object-cover"/>
        @endif
        <div class="space-y-4">
            <div>
                <h1 class="text-3xl font-semibold text-slate-50">{{ $movie->title }} <span class="text-2xl font-normal text-slate-400">({{ $movie->year ?? '—' }})</span></h1>
                <p class="mt-2 text-sm text-slate-400">IMDb {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }} голосов • Weighted {{ $movie->weighted_score }}</p>
            </div>
            @if($movie->plot)
                <p class="text-sm leading-relaxed text-slate-200">{{ $movie->plot }}</p>
            @endif
            @if($movie->genres)
                <p class="text-sm text-slate-400">Жанры: {{ implode(', ', $movie->genres) }}</p>
            @endif
        </div>
    </div>
</div>
@endsection
