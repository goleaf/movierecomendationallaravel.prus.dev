<x-filament-panels::page>
    <div class="space-y-4">
        <form id="filters" class="grid gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 md:grid-cols-6">
            <select name="days" class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                @foreach ([3, 7, 14, 30] as $d)
                    <option value="{{ $d }}">{{ $d }} дней</option>
                @endforeach
            </select>
            <select name="type" class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
                <option value="">Тип</option>
                <option value="movie">Фильмы</option>
                <option value="series">Сериалы</option>
                <option value="animation">Мультики</option>
            </select>
            <input name="genre" placeholder="Жанр" class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
            <input name="yf" placeholder="Год от" type="number" class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
            <input name="yt" placeholder="Год до" type="number" class="rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40">
            <button type="button" onclick="applyFilters()" class="rounded-xl border border-sky-500 bg-sky-500/10 px-3 py-2 text-sm font-medium text-sky-200 transition hover:bg-sky-500/20">Показать</button>
        </form>

        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <iframe id="trendsframe" src="{{ route('trends') }}" class="h-[1200px] w-full rounded-xl border-0"></iframe>
        </div>
    </div>

    <script>
        function applyFilters() {
            const form = document.getElementById('filters');
            const params = new URLSearchParams(new FormData(form)).toString();
            document.getElementById('trendsframe').src = '{{ route('trends') }}?' + params;
        }
    </script>
</x-filament-panels::page>
