
@php
    $aiRecord = $aiFinancialAnalysis ?? null;
    $analysis = $aiRecord['analysis'] ?? null;

    $healthStyles = [
        'stable' => [
            'label' => 'Stable',
            'color' => 'success',
            'icon' => 'fa-circle-check',
        ],
        'attention' => [
            'label' => 'Needs Attention',
            'color' => 'warning',
            'icon' => 'fa-triangle-exclamation',
        ],
        'critical' => [
            'label' => 'Critical',
            'color' => 'danger',
            'icon' => 'fa-circle-exclamation',
        ],
        'insufficient_data' => [
            'label' => 'Insufficient Data',
            'color' => 'secondary',
            'icon' => 'fa-circle-info',
        ],
    ];

    $healthKey = $analysis['health'] ?? 'insufficient_data';

    $health = $healthStyles[$healthKey]
        ?? $healthStyles['insufficient_data'];

    $directionIcons = [
        'up' => 'fa-arrow-trend-up',
        'down' => 'fa-arrow-trend-down',
        'stable' => 'fa-minus',
        'unknown' => 'fa-circle-question',
    ];

    $priorityColors = [
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'secondary',
    ];

    $generatedAt = null;

    if (! empty($aiRecord['generated_at'])) {
        try {
            $generatedAt = \Illuminate\Support\Carbon::parse(
                $aiRecord['generated_at']
            );
        } catch (\Throwable $exception) {
            $generatedAt = null;
        }
    }
@endphp

<section class="mb-4" aria-labelledby="ai-advisor-heading">

    <div class="card border-0 shadow-sm">

        <div class="card-body p-4">

            {{-- Header --}}
            <div class="d-flex flex-wrap justify-content-between
                        align-items-start gap-3 mb-4">

                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">

                        <i class="fa-solid fa-wand-magic-sparkles text-primary"></i>

                        <h2 id="ai-advisor-heading"
                            class="h5 fw-bold mb-0">
                            AI Financial Advisor
                        </h2>

                        <span class="badge text-bg-secondary">
                            Gemini
                        </span>

                    </div>

                    <p class="text-secondary small mb-0">
                        Personalised insights based on your recorded
                        financial activity and spending patterns.
                    </p>
                </div>

                <form
                    method="POST"
                    action="{{ route('financial-ai.generate', $period->id) }}"
                    id="generateFinancialAIForm"
                >
                    @csrf

                    <button type="submit"
                            class="btn btn-primary"
                            id="generateFinancialAIButton">

                        <i class="fa-solid fa-wand-magic-sparkles me-2"></i>

                        {{ $analysis ? 'Refresh Analysis' : 'Generate Analysis' }}

                    </button>
                </form>

            </div>

            @error('financial_ai')
                <div class="alert alert-danger small">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    {{ $message }}
                </div>
            @enderror

            @if(!$analysis)

                {{-- Empty state --}}
                <div class="text-center py-5">

                    <div class="mb-3">
                        <i class="fa-solid fa-brain fa-3x text-secondary"></i>
                    </div>

                    <h3 class="h6 fw-bold mb-2">
                        Discover Your Financial Patterns
                    </h3>

                    <p class="text-secondary small mx-auto mb-0"
                       style="max-width: 500px;">

                        Generate an AI-powered financial review to
                        identify spending trends, budget risks,
                        opportunities to save, and recommendations
                        based on your financial records.

                    </p>

                </div>

            @else

                {{-- Financial health --}}
                <div class="d-flex flex-wrap align-items-center
                            justify-content-between gap-3 mb-4 p-3 rounded"
                     style="background: var(--bs-tertiary-bg);">

                    <div class="d-flex align-items-center gap-3">

                        <span class="text-{{ $health['color'] }}">
                            <i class="fa-solid {{ $health['icon'] }} fa-xl"></i>
                        </span>

                        <div>
                            <div class="text-secondary small mb-1">
                                Financial Health Assessment
                            </div>

                            <div class="fw-bold">
                                {{ $health['label'] }}
                            </div>
                        </div>

                    </div>

                    <span class="badge text-bg-{{ $health['color'] }}">
                        {{ $health['label'] }}
                    </span>

                </div>

                {{-- Overview --}}
                <div class="mb-4">

                    <h3 class="h6 fw-bold mb-2">
                        Financial Overview
                    </h3>

                    <p class="text-secondary mb-0">
                        {{ $analysis['overview'] ?? '' }}
                    </p>

                </div>

                {{-- Trends and recommendations --}}
                <div class="row g-4 mb-4">

                    <div class="col-lg-6">

                        <h3 class="h6 fw-bold mb-3">
                            <i class="fa-solid fa-chart-line me-2 text-primary"></i>
                            Spending Trends
                        </h3>

                        @forelse(($analysis['trends'] ?? []) as $trend)

                            @php
                                $direction = $trend['direction'] ?? 'unknown';
                                $directionIcon = $directionIcons[$direction]
                                    ?? $directionIcons['unknown'];

                                $significance = $trend['significance'] ?? 'low';

                                $significanceColor = $priorityColors[$significance]
                                    ?? 'secondary';
                            @endphp

                            <div class="border rounded p-3 mb-3">

                                <div class="d-flex align-items-start
                                            justify-content-between gap-2 mb-2">

                                    <div class="fw-semibold">

                                        <i class="fa-solid {{ $directionIcon }}
                                                  me-2 text-secondary"></i>

                                        {{ $trend['title'] ?? 'Financial Trend' }}

                                    </div>

                                    <span class="badge text-bg-{{ $significanceColor }}">
                                        {{ ucfirst($significance) }}
                                    </span>

                                </div>

                                <p class="small text-secondary mb-0">
                                    {{ $trend['description'] ?? '' }}
                                </p>

                            </div>

                        @empty

                            <p class="text-secondary small">
                                Not enough comparative data to identify
                                meaningful spending trends.
                            </p>

                        @endforelse

                    </div>

                    <div class="col-lg-6">

                        <h3 class="h6 fw-bold mb-3">
                            <i class="fa-solid fa-lightbulb me-2 text-warning"></i>
                            Recommendations
                        </h3>

                        @forelse(($analysis['recommendations'] ?? []) as $recommendation)

                            @php
                                $priority = $recommendation['priority'] ?? 'low';

                                $priorityColor = $priorityColors[$priority]
                                    ?? 'secondary';
                            @endphp

                            <div class="border rounded p-3 mb-3">

                                <div class="d-flex justify-content-between
                                            align-items-start gap-2 mb-2">

                                    <div class="fw-semibold">
                                        {{ $recommendation['title'] ?? 'Recommendation' }}
                                    </div>

                                    <span class="badge text-bg-{{ $priorityColor }}">
                                        {{ ucfirst($priority) }} priority
                                    </span>

                                </div>

                                <p class="text-secondary small mb-2">
                                    {{ $recommendation['description'] ?? '' }}
                                </p>

                                <span class="text-secondary small">
                                    {{ ucwords(str_replace(
                                        '_',
                                        ' ',
                                        $recommendation['category'] ?? 'general'
                                    )) }}
                                </span>

                            </div>

                        @empty

                            <p class="text-secondary small">
                                No specific recommendations were generated.
                            </p>

                        @endforelse

                    </div>

                </div>

                {{-- Positive observations --}}
                @if(!empty($analysis['positive_observations']))

                    <div class="border-top pt-4 mb-3">

                        <h3 class="h6 fw-bold mb-3">
                            <i class="fa-solid fa-circle-check
                                      text-success me-2"></i>
                            Positive Financial Habits
                        </h3>

                        <div class="row g-3">

                            @foreach($analysis['positive_observations'] as $observation)

                                <div class="col-md-6 col-xl-4">
                                    <div class="rounded p-3 h-100"
                                         style="background: var(--bs-tertiary-bg);">

                                        <i class="fa-solid fa-check
                                                  text-success me-2"></i>

                                        <span class="small">
                                            {{ $observation }}
                                        </span>

                                    </div>
                                </div>

                            @endforeach

                        </div>

                    </div>

                @endif

            @endif

            {{-- Footer --}}
            <div class="border-top pt-3 mt-4">

                <div class="d-flex flex-wrap align-items-center
                            justify-content-between gap-2">

                    <span class="text-secondary small">

                        <i class="fa-solid fa-circle-info me-1"></i>

                        AI-generated guidance based on recorded data.
                        Verify recommendations before acting.

                    </span>

                    @if($generatedAt)
                        <span class="text-secondary small">
                            <i class="fa-solid fa-clock me-1"></i>
                            Generated {{ $generatedAt->format('d M Y, H:i') }}
                        </span>
                    @endif

                </div>

            </div>

        </div>

    </div>

</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('generateFinancialAIForm');
    const button = document.getElementById('generateFinancialAIButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function () {
        button.disabled = true;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Analysing Your Finances...';
    });
});
</script>
@endpush
