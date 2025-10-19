@extends('layouts.app')
@section('title', $movie->title)
@section('content')
<div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-8 shadow-sm shadow-slate-900/40">
    <div class="grid gap-8 md:grid-cols-[220px,1fr]">
        @if($movie->poster_url)
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
                <img class="w-full object-cover" src="{{ $movie->poster_url }}" alt="{{ $movie->title }}">
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <h2 class="text-3xl font-semibold text-white">{{ $movie->title }} <span class="text-slate-400">({{ $movie->year ?? '—' }})</span></h2>
                <p class="mt-2 text-sm text-slate-400">
                    IMDb {{ $movie->imdb_rating ?? '—' }} • {{ number_format($movie->imdb_votes ?? 0, 0, '.', ' ') }} голосов • Weighted {{ $movie->weighted_score }}
                </p>
            </div>

            @if($movie->plot)
                <p class="text-base text-slate-200">{{ $movie->plot }}</p>
            @endif

            @if($movie->genres)
                <div class="flex flex-wrap gap-2 text-sm text-slate-300">
                    @foreach($movie->genres as $genre)
                        <span class="rounded-full border border-slate-700 bg-slate-800/60 px-3 py-1">{{ $genre }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
