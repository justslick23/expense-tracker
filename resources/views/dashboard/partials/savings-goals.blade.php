
@use('App\Support\Money')

<section class="dashboard-savings mb-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Savings Goals</h2>
            <p class="text-secondary small mb-0">
                Track your progress towards your financial targets.
            </p>
        </div>

        <a href="{{ route('savings.index') }}"
           class="btn btn-sm btn-outline-secondary">
            View all goals
            <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>

    {{-- Overview cards --}}
    <div class="row g-3 mb-3">

        <div class="col-md-4">
            <div class="card dashboard-savings-stat h-100">
                <div class="d-flex justify-content-between gap-2 mb-3">
                    <span class="small text-secondary">Total saved</span>
                    <i class="fa-solid fa-piggy-bank text-success"></i>
                </div>

                <div class="h4 fw-bold savings-number mb-1">
                    {{ Money::format($savingsSummary['total_saved']) }}
                </div>

                <div class="small text-secondary">
                    Across {{ $savingsSummary['active_count'] }} active goals
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card dashboard-savings-stat h-100">
                <div class="d-flex justify-content-between gap-2 mb-3">
                    <span class="small text-secondary">Combined target</span>
                    <i class="fa-solid fa-bullseye text-primary"></i>
                </div>

                <div class="h4 fw-bold savings-number mb-1">
                    {{ Money::format($savingsSummary['total_target']) }}
                </div>

                <div class="small text-secondary">
                    {{ Money::format($savingsSummary['remaining']) }}
                    remaining
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card dashboard-savings-stat h-100">
                <div class="d-flex justify-content-between gap-2 mb-3">
                    <span class="small text-secondary">Overall progress</span>
                    <i class="fa-solid fa-chart-line text-info"></i>
                </div>

                <div class="h4 fw-bold savings-number mb-2">
                    {{ number_format($savingsSummary['overall_progress'], 1) }}%
                </div>

                <div class="progress dashboard-savings-progress"
                     role="progressbar"
                     aria-label="Overall savings progress"
                     aria-valuenow="{{ $savingsSummary['overall_progress'] }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    <div class="progress-bar"
                         style="width: {{ $savingsSummary['overall_progress'] }}%">
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Individual goals --}}
    <div class="card dashboard-savings-panel">
        <div class="dashboard-savings-heading">
            <div>
                <h3 class="h6 fw-bold mb-1">Your savings goals</h3>
                <p class="small text-secondary mb-0">
                    Progress towards your active financial targets.
                </p>
            </div>

            <a href="{{ route('savings.create') }}"
               class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-plus me-1"></i>
                New goal
            </a>
        </div>

        @forelse ($savingsSummary['goals'] as $goal)
            <div class="dashboard-savings-goal">

                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <a href="{{ route('savings.show', $goal['id']) }}"
                           class="fw-semibold text-decoration-none">
                            {{ $goal['name'] }}
                        </a>

                        <div class="small text-secondary mt-1">
                            @if ($goal['target_date'])
                                <i class="fa-regular fa-calendar me-1"></i>
                                Target: {{ $goal['target_date']->format('d M Y') }}
                            @else
                                No deadline
                            @endif
                        </div>
                    </div>

                    @if ($goal['target_reached'])
                        <span class="badge text-bg-success">Target reached</span>
                    @else
                        <span class="small fw-semibold">
                            {{ number_format($goal['progress'], 1) }}%
                        </span>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-2">
                    <div class="fw-semibold savings-number">
                        {{ Money::format($goal['saved']) }}
                        <span class="small text-secondary fw-normal">
                            / {{ Money::format($goal['target']) }}
                        </span>
                    </div>

                    <div class="small text-secondary">
                        {{ Money::format($goal['remaining']) }} left
                    </div>
                </div>

                <div class="progress dashboard-savings-progress"
                     role="progressbar"
                     aria-label="{{ $goal['name'] }} progress"
                     aria-valuenow="{{ $goal['progress'] }}"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    <div class="progress-bar"
                         style="width: {{ $goal['progress'] }}%"></div>
                </div>

            </div>
        @empty
            <div class="dashboard-savings-empty">
                <i class="fa-solid fa-piggy-bank mb-3"></i>

                <h4 class="h6 fw-semibold">No active savings goals</h4>

                <p class="small text-secondary mb-3">
                    Start saving towards something important to you.
                </p>

                <a href="{{ route('savings.create') }}"
                   class="btn btn-primary btn-sm">
                    Create your first goal
                </a>
            </div>
        @endforelse

        @if ($savingsSummary['active_count'] > 4)
            <div class="dashboard-savings-footer">
                <a href="{{ route('savings.index') }}"
                   class="small text-decoration-none">
                    View all {{ $savingsSummary['active_count'] }} goals
                    <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        @endif
    </div>

    <p class="small text-secondary mt-2 mb-0">
        <i class="fa-solid fa-circle-info me-1"></i>
        Savings allocations are tracked separately from actual income and expenses.
    </p>

</section>

