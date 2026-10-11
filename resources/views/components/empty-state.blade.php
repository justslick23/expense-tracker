@props(['title', 'icon' => 'fa-inbox', 'text' => null])
<div class="empty-state">
    <div class="empty-icon"><i class="fa-solid {{ $icon }}"></i></div>
    <h2 class="h5">{{ $title }}</h2>
    @if ($text)<p class="text-secondary mb-3">{{ $text }}</p>@endif
    {{ $slot }}
</div>