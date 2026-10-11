@props(['tone' => 'neutral', 'icon' => null])
<span {{ $attributes->class(['chip', "tone-$tone"]) }}>@if ($icon)<i class="fa-solid {{ $icon }}"></i>@endif{{ $slot }}</span>