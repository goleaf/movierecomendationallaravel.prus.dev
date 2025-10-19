@extends('layouts.app')
@section('title','Поиск')
@section('content')
<div class="space-y-6">
    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-6 shadow-sm">
        <form method="get" class="grid gap-3 md:grid-cols-6">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Название или tt..."
                class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
            >
            <select
                name="type"
                class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
            >
                <option value="">Тип</option>
                <option value="movie" @selected(($type ?? '')==='movie')>Фильмы</option>
                <option value="series" @selected(($type ?? '')==='series')>Сериалы</option>
                <option value="animation" @selected(($type ?? '')==='animation')>Мультики</option>
            </select>
            <input
                type="text"
                name="genre"
                value="{{ $genre }}"
                placeholder="Жанр"
                class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
            >
            <input
                type="number"
                name="yf"
                value="{{ $yf }}"
                placeholder="Год от"
                class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
            >
            <input
                type="number"
                name="yt"
                value="{{ $yt }}"
                placeholder="Год до"
                class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40"
            >
            <button class="rounded-xl border border-sky-500 bg-sky-500/10 px-3 py-2 text-sm font-medium text-sky-200 transition hover:bg-sky-500/20">Искать</button>
        </form>
    </div>

    @if($items->isEmpty())
        <p class="text-sm text-slate-400">Ничего не найдено.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($items as $m)
                <a
                    href="{{ route('movies.show',$m) }}"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition duration-200 hover:border-slate-700 hover:bg-slate-900"
                >
                    @if($m->poster_url)
                        <img src="{{ $m->poster_url }}" alt="{{ $m->title }}" class="mb-4 aspect-[2/3] w-full rounded-xl object-cover"/>
                    @endif
                    <div class="space-y-1">
                        <p class="text-base font-semibold text-slate-50">{{ $m->title }} <span class="font-normal text-slate-400">({{ $m->year ?? '—' }})</span></p>
                        <p class="text-sm text-slate-400">IMDb: {{ $m->imdb_rating ?? '—' }} • {{ number_format($m->imdb_votes ?? 0, 0, '.', ' ') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
