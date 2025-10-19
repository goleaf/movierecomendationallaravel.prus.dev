<x-filament-panels::page>
  <form id="filters" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:12px;">
    <select name="days">@foreach([3,7,14,30] as $d)<option value="{{ $d }}">{{ $d }} дней</option>@endforeach</select>
    <select name="type"><option value="">Тип</option><option value="movie">Фильмы</option><option value="series">Сериалы</option><option value="animation">Мультики</option></select>
    <input name="genre" placeholder="Жанр"><input name="yf" placeholder="Год от" type="number"><input name="yt" placeholder="Год до" type="number">
    <button type="button" onclick="apply()">Показать</button>
  </form>
  <iframe id="trendsframe" src="{{ route('trends') }}" style="width:100%;height:1200px;border:0;"></iframe>
  <script>
    function apply(){ const f=document.getElementById('filters'); const p=new URLSearchParams(new FormData(f)).toString(); document.getElementById('trendsframe').src='{{ route('trends') }}?'+p; }
  </script>
</x-filament-panels::page>
