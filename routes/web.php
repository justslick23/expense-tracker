<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PeriodController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\SavingsGoalController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\FinancialAIController;


Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::middleware('auth')
    ->get('/home', [DashboardController::class, 'index'])
    ->name('home');

Route::middleware('auth')->controller(PeriodController::class)->prefix('periods')->name('periods.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{period}/close', 'close')->name('close');
    Route::post('/{period}/close', 'closeStore')->name('close.store');
});

Route::middleware('auth')->group(function () {
    Route::resource('categories', CategoryController::class)->except(['show', 'destroy']);
    Route::patch('categories/{category}/archive', [CategoryController::class, 'archive'])->name('categories.archive');
    Route::patch('categories/{category}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::get('/reports', [
        FinancialReportController::class,
        'index'
    ])->name('reports.index');
    
    Route::get('/reports/history', [
        FinancialReportController::class,
        'history'
    ])->name('reports.history');

    Route::get('/reports/export/csv', [
        FinancialReportController::class,
        'export'
    ])->name('reports.export');
    
    Route::get('/reports/history/export/csv', [
        FinancialReportController::class,
        'exportHistory'
    ])->name('reports.history.export');

    Route::post(
        '/financial-ai/{period}/generate',
        [FinancialAIController::class, 'generate']
    )
        ->middleware('throttle:3,1')
        ->name('financial-ai.generate');
/*
|--------------------------------------------------------------------------
| Recurring Expenses
|--------------------------------------------------------------------------
*/

Route::prefix('recurring')
->name('recurring.')
->controller(RecurringExpenseController::class)
->group(function () {
    Route::get('/', 'index')->name('index');

    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');

    Route::post('/generate', 'generate')->name('generate');

    Route::post('/occurrences/{occurrence}/pay', 'pay')
        ->name('pay');

    Route::post('/occurrences/{occurrence}/skip', 'skip')
        ->name('skip');

    Route::post('/occurrences/{occurrence}/reopen', 'reopen')
        ->name('reopen');

    Route::get('/{recurring}/edit', 'edit')->name('edit');

    Route::put('/{recurring}', 'update')->name('update');

    Route::delete('/{recurring}', 'destroy')->name('destroy');
});


Route::middleware('auth')->group(function () {

    Route::get('/receipts', [ReceiptController::class, 'index'])
    ->name('receipts.index');


    Route::get('/receipts/create', [
        ReceiptController::class, 'create'
    ])->name('receipts.create');

    Route::post('/receipts', [
        ReceiptController::class, 'store'
    ])->name('receipts.store');

    Route::get('/receipts/{receipt}', [
        ReceiptController::class, 'show'
    ])->name('receipts.show');

    Route::get('/receipts/{receipt}/image', [
        ReceiptController::class, 'image'
    ])->name('receipts.image');
});

Route::post(
    '/receipts/{receipt}/scan',
    [ReceiptController::class, 'scan']
)->name('receipts.scan')->middleware('throttle:5,1');

Route::get('/receipts/{receipt}/review', [
    ReceiptController::class, 'review'
])->name('receipts.review');

Route::put('/receipts/{receipt}/review', [
    ReceiptController::class, 'updateReview'
])->name('receipts.review.update');

Route::post(
    '/receipts/{receipt}/confirm',
    [ReceiptController::class, 'confirm']
)->name('receipts.confirm');

Route::get(
    '/receipts/{receipt}/image',
    [ReceiptController::class, 'image']
)->name('receipts.image');

/*
|--------------------------------------------------------------------------
| Savings Goals
|--------------------------------------------------------------------------
*/

Route::prefix('savings')
    ->name('savings.')
    ->controller(SavingsGoalController::class)
    ->group(function () {

        Route::get('/', 'index')->name('index');

        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');

        Route::get('/{goal}', 'show')->name('show');
        Route::get('/{goal}/edit', 'edit')->name('edit');
        Route::put('/{goal}', 'update')->name('update');

        Route::post('/{goal}/contributions', 'contribute')
            ->name('contribute');

        Route::post('/{goal}/withdrawals', 'withdraw')
            ->name('withdraw');

        Route::post('/{goal}/complete', 'complete')
            ->name('complete');

        Route::post('/{goal}/reopen', 'reopen')
            ->name('reopen');

        Route::post('/{goal}/archive', 'archive')
            ->name('archive');
    });


});

Route::middleware('auth')->resource('incomes', IncomeController::class)->except('show');
Route::middleware('auth')
    ->resource('expenses', ExpenseController::class)
    ->except(['show']);

Route::middleware('auth')
    ->resource('budgets', BudgetController::class)
    ->except(['show']);