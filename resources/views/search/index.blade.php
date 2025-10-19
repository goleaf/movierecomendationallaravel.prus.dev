@extends('layouts.app')
@section('title','Поиск')
@section('content')
<div class="rounded-3xl border border-slate-800 bg-slate-900/60 p-6 shadow-sm shadow-slate-900/40">
    <form method="get" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="text" name="q" value="{{ $q }}" placeholder="Название или tt..." class="rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <select name="type" class="rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="">Тип</option>
            <option value="movie" @selected(($type ?? '')==='movie')>Фильмы</option>
            <option value="series" @selected(($type ?? '')==='series')>Сериалы</option>
            <option value="animation" @selected(($type ?? '')==='animation')>Мультики</option>
        </select>
        <input type="text" name="genre" value="{{ $genre }}" placeholder="Жанр" class="rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <input type="number" name="yf" value="{{ $yf }}" placeholder="Год от" class="rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <input type="number" name="yt" value="{{ $yt }}" placeholder="Год до" class="rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white placeholder:text-slate-500 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <button class="rounded-xl bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-300 focus:ring-offset-2 focus:ring-offset-slate-900">Искать</button>
    </form>
</div>

@if($items->isEmpty())
    <p class="text-sm text-slate-400">Ничего не найдено</p>
@else
    <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($items as $m)
            <a href="{{ route('movies.show',$m) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/50 transition hover:border-slate-700 hover:bg-slate-900">
                @if($m->poster_url)
                    <div class="aspect-[2/3] w-full overflow-hidden bg-slate-950">
                        <img src="{{ $m->poster_url }}" alt="{{ $m->title }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
                    </div>
                @endif
                <div class="flex flex-1 flex-col gap-2 p-5">
                    <div>
                        <p class="text-lg font-semibold text-white">{{ $m->title }}</p>
                        <p class="text-sm text-slate-400">{{ $m->year ?? '—' }}</p>
                    </div>
                    <p class="text-sm text-slate-400">IMDb: {{ $m->imdb_rating ?? '—' }} • {{ number_format($m->imdb_votes ?? 0, 0, '.', ' ') }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
