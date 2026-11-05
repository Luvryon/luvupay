{{-- Enterprise bundle (includes Community). Falls back to Community if the enterprise file is not deployed. --}}
@php($agEnterprise = is_file(public_path('assets/ag-grid-32.3.3/ag-grid-enterprise.min.noStyle.js')))
<script src="/assets/ag-grid-32.3.3/{{ $agEnterprise ? 'ag-grid-enterprise.min.noStyle.js' : 'ag-grid-community.min.noStyle.js' }}" defer></script>
<script src="/js/ag-grid-luvu.js?v={{ @filemtime(public_path('js/ag-grid-luvu.js')) }}" defer></script>
