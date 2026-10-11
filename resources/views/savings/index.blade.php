
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Savings Goals')

@section('content')
<div class="savings-page">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Savings Goals</h1>
            <p class="text-secondary mb-0">
                Set financial targets and track your saving progress.
            </p>
        </div>

        <a href="{{ route('savings.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-2"></i>
            New savings goal
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa-solid fa-circle-check me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Overview cards --}}
    <div class="row g-3 mb-4">
        @foreach ([
            [
                'label' => 'Total saved',
                'value' => Money::format($summary['total_saved']),
                'icon' => 'fa-piggy-bank',
                'color' => 'success'
            ],
            [
                'label' => 'Combined target',
                'value' => Money::format($summary['total_target']),
                'icon' => 'fa-bullseye',
                'color' => 'primary'
            ],
            [
                'label' => 'Active goals',
                'value' => $summary['active_count'],
                'icon' => 'fa-chart-line',
                'color' => 'info'
            ],
            [
                'label' => 'Completed goals',
                'value' => $summary['completed_count'],
                'icon' => 'fa-circle-check',
                'color' => 'success'
            ],
        ] as $item)
            <div class="col-sm-6 col-xl-3">
                <div class="card savings-stat h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <span class="small text-secondary">
                            {{ $item['label'] }}
                        </span>
                        <i class="fa-solid {{ $item['icon'] }} text-{{ $item['color'] }}"></i>
                    </div>
                    <div class="h4 fw-bold savings-number mb-0">
                        {{ $item['value'] }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Filter tabs --}}
    <div class="savings-filter-bar mb-4">
        @foreach ([
            'active' => 'Active',
            'completed' => 'Completed',
            'archived' => 'Archived',
            'all' => 'All goals',
        ] as $value => $label)
            <a href="{{ route('savings.index', ['status' => $value]) }}"
               class="savings-filter {{ $status === $value ? 'active' : '' }}"
               @if ($status === $value) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Goal cards --}}
    @if ($goals->isEmpty())
        <div class="card savings-empty">
            <i class="fa-solid fa-bullseye mb-3"></i>

            <h2 class="h5 fw-bold">No savings goals found</h2>

            <p class="text-secondary">
                @if ($status === 'active')
                    Create a goal to start tracking your savings.
                @else
                    There are no goals in this category.
                @endif
            </p>

            <a href="{{ route('savings.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus me-2"></i>
                Create savings goal
            </a>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach ($goals as $goal)
                @php
                    $saved = (int) $goal->starting_amount
                        + (int) ($goal->contributions_total ?? 0)
                        - (int) ($goal->withdrawals_total ?? 0);

                    $remaining = max(0, $goal->target_amount - $saved);

                    $progress = $goal->target_amount > 0
                        ? min(100, max(0, ($saved / $goal->target_amount) * 100))
                        : 0;
                @endphp

                <div class="col-md-6 col-xl-4">
                    <div class="card savings-goal-card h-100">

                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="savings-goal-icon">
                                <i class="fa-solid fa-bullseye"></i>
                            </div>

                            @if ($goal->isCompleted())
                                <span class="badge text-bg-success">Completed</span>
                            @elseif ($goal->isArchived())
                                <span class="badge text-bg-secondary">Archived</span>
                            @elseif ($saved >= $goal->target_amount)
                                <span class="badge text-bg-info">Target reached</span>
                            @else
                                <span class="badge text-bg-primary">In progress</span>
                            @endif
                        </div>

                        <h2 class="h5 fw-bold mb-2">
                            {{ $goal->name }}
                        </h2>

                        @if ($goal->description)
                            <p class="small text-secondary savings-description">
                                {{ $goal->description }}
                            </p>
                        @endif

                        <div class="mt-auto">
                            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
                                <span class="h4 fw-bold savings-number mb-0">
                                    {{ Money::format($saved) }}
                                </span>
                                <span class="text-secondary small">
                                    of {{ Money::format($goal->target_amount) }}
                                </span>
                            </div>

                            <div class="progress savings-progress mb-2"
                                 role="progressbar"
                                 aria-label="Savings progress"
                                 aria-valuenow="{{ round($progress, 1) }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                                <div class="progress-bar"
                                     style="width: {{ round($progress, 1) }}%"></div>
                            </div>

                            <div class="d-flex justify-content-between gap-2 small mb-3">
                                <span class="fw-semibold">
                                    {{ number_format($progress, 1) }}%
                                </span>

                                <span class="text-secondary">
                                    {{ Money::format($remaining) }} remaining
                                </span>
                            </div>

                            <div class="d-flex align-items-center justify-content-between gap-2 border-top pt-3">
                                <div class="small text-secondary">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    {{ $goal->target_date
                                        ? $goal->target_date->format('d M Y')
                                        : 'No deadline' }}
                                </div>

                                <a href="{{ route('savings.show', $goal) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    View goal
                                    <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        {{ $goals->links('pagination::bootstrap-5') }}
    @endif

</div>
@endsection
