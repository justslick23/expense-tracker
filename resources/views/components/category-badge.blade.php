@props(['category'])
<span class="cat-badge">
    <span class="cat-dot" style="--c: {{ $category->color ?? 'var(--muted)' }}"><i class="fa-solid {{ $category->icon ?? 'fa-tag' }}"></i></span>{{ $category->name }}
</span>