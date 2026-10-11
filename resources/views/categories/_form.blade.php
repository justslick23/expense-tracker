@php $c = $category ?? null; @endphp
<form method="POST" action="{{ $c ? route('categories.update', $c) : route('categories.store') }}">
    @csrf @if ($c) @method('PUT') @endif

    <div class="mb-3">
        <label class="form-label d-block">Type</label>
        @if ($c)
            <x-chip>{{ ucfirst($c->type) }}</x-chip>
            <div class="form-text">The type cannot be changed, because it would alter the meaning of past records.</div>
        @else
            <div class="btn-group">
                @foreach (\App\Models\Category::TYPES as $t)
                    <input type="radio" class="btn-check" name="type" id="type-{{ $t }}" value="{{ $t }}" @checked(old('type', request('type', 'expense')) === $t)>
                    <label class="btn btn-outline-secondary" for="type-{{ $t }}">{{ ucfirst($t) }}</label>
                @endforeach
            </div>
            @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @endif
    </div>

    <div class="mb-3">
        <label class="form-label" for="name">Name</label>
        <input id="name" name="name" value="{{ old('name', $c?->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="60" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="color">Color</label>
        <input id="color" type="color" name="color" value="{{ old('color', $c?->color ?? '#006BB6') }}" class="form-control form-control-color">
        @error('color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="mb-4">
        <label class="form-label">Icon</label>
        <div class="icon-grid">
            <label><input type="radio" name="icon" value="" @checked(! old('icon', $c?->icon))><span title="No icon"><i class="fa-solid fa-ban"></i></span></label>
            @foreach (\App\Models\Category::ICONS as $icon)
                <label><input type="radio" name="icon" value="{{ $icon }}" @checked(old('icon', $c?->icon) === $icon)><span><i class="fa-solid {{ $icon }}"></i></span></label>
            @endforeach
        </div>
        @error('icon')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <button class="btn btn-primary">{{ $c ? 'Save changes' : 'Create category' }}</button>
    <a href="{{ route('categories.index') }}" class="btn btn-link">Cancel</a>
</form>