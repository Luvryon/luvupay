@props(['groups'])
<nav class="nav" aria-label="Console">
    @foreach($groups as $heading => $items)
        <div class="nav-group">
            <p class="nav-h">{{ $heading }}</p>
            @foreach($items as $item)
                <a href="{{ $item['href'] }}" class="nav-a {{ $item['active'] ? 'on' : '' }}" @if($item['active']) aria-current="page" @endif @if($item['external'] ?? false) data-no-ajax @endif>
                    <x-ui.icon :name="$item['icon']" :size="18" />
                    <span>{{ $item['label'] }}</span>
                    @if($item['external'] ?? false)<span class="nav-ext">↗</span>@endif
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
