@props(['title', 'subtitle' => null])
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">{{ $title }}</h1>
        @if ($subtitle)<p class="text-secondary mb-0">{{ $subtitle }}</p>@endif
    </div>
    @if ($slot->isNotEmpty())<div class="d-flex gap-2 flex-wrap">{{ $slot }}</div>@endif
</div>