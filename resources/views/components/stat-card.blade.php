@props(['label', 'icon', 'value', 'tone' => 'info', 'trend' => null, 'trendTone' => 'neutral', 'trendIcon' => null, 'sample' => false])
<div class="card p-3 h-100">
    <div class="d-flex align-items-center gap-3">
        <span class="stat-icon tone-{{ $tone }}"><i class="fa-solid {{ $icon }}"></i></span>
        <div>
            <div class="small text-secondary">{{ $label }}</div>
            <div class="stat-value num">{{ $value }}</div>
        </div>
    </div>
    @if ($trend || $sample)
        <div class="d-flex flex-wrap gap-2 mt-3">
            @if ($trend)<x-chip :tone="$trendTone" :icon="$trendIcon">{{ $trend }}</x-chip>@endif
            @if ($sample)<x-chip>Sample</x-chip>@endif
        </div>
    @endif
</div>