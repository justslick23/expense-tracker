<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClosePeriodRequest;
use App\Http\Requests\StorePeriodRequest;
use App\Models\FinancialPeriod;
use App\Services\PeriodService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class PeriodController extends Controller
{
    public function __construct(private PeriodService $periods) {}

    public function index(Request $request)
    {
        return view('periods.index', [
            'periods' => $request->user()->financialPeriods()->latest('start_date')->get(),
            'current' => $this->periods->current($request->user()),
        ]);
    }

    public function create(Request $request)
    {
        if ($this->periods->current($request->user())) {
            return redirect()->route('periods.index')->with('error', 'Close the current period before starting a new one.');
        }

        return view('periods.create');
    }

    public function store(StorePeriodRequest $request)
    {
        $d = $request->validated();

        $this->periods->start(
            $request->user(),
            $d['name'],
            Carbon::parse($d['start_date']),
            isset($d['end_date']) ? Carbon::parse($d['end_date']) : null,
            Money::parse($d['opening_balance'] ?? '0'),
        );

        return redirect()->route('periods.index')->with('success', 'Financial period started.');
    }

    public function close(FinancialPeriod $period)
    {
        Gate::authorize('close', $period);

        if (! $period->isOpen()) {
            return redirect()->route('periods.index')->with('error', 'That period is already closed.');
        }

        return view('periods.close', compact('period'));
    }

    public function closeStore(ClosePeriodRequest $request, FinancialPeriod $period)
    {
        Gate::authorize('close', $period);

        $this->periods->close($period, Carbon::parse($request->validated('end_date')));

        return redirect()->route('periods.index')->with('success', 'Financial period closed.');
    }
}