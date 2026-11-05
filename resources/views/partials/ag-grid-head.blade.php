{{-- AG Grid styles + licence key (AG_GRID_LICENSE_KEY in .env → config('services.ag_grid.license_key')) --}}
@php($agKey = trim((string) config('services.ag_grid.license_key')))
@if($agKey !== '')<meta name="ag-grid-license" content="{{ $agKey }}">@endif
<link rel="stylesheet" href="/assets/ag-grid-32.3.3/ag-grid.min.css">
<link rel="stylesheet" href="/assets/ag-grid-32.3.3/ag-theme-quartz.min.css">
<link rel="stylesheet" href="/css/ag-grid-luvu.css?v={{ @filemtime(public_path('css/ag-grid-luvu.css')) }}">
