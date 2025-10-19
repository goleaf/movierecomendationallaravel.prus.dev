<x-filament-panels::page>
  <form id="filters" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:12px;">
    <select name="days">@foreach([3,7,14,30] as $d)<option value="{{ $d }}">{{ __('admin.trends.days_option', ['days' => $d]) }}</option>@endforeach</select>
    <select name="type"><option value="">{{ __('admin.trends.type_placeholder') }}</option><option value="movie">{{ __('admin.trends.types.movie') }}</option><option value="series">{{ __('admin.trends.types.series') }}</option><option value="animation">{{ __('admin.trends.types.animation') }}</option></select>
    <input name="genre" placeholder="{{ __('admin.trends.genre_placeholder') }}"><input name="yf" placeholder="{{ __('admin.trends.year_from_placeholder') }}" type="number"><input name="yt" placeholder="{{ __('admin.trends.year_to_placeholder') }}" type="number">
    <button type="button" onclick="apply()">{{ __('admin.trends.apply') }}</button>
  </form>
  <iframe id="trendsframe" src="{{ route('trends') }}" style="width:100%;height:1200px;border:0;"></iframe>
  <script>
    function apply(){ const f=document.getElementById('filters'); const p=new URLSearchParams(new FormData(f)).toString(); document.getElementById('trendsframe').src='{{ route('trends') }}?'+p; }
  </script>
</x-filament-panels::page>
