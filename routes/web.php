<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Credentials\CredentialController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\WorkItems\WorkItemController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VIRA CRM — routes
|--------------------------------------------------------------------------
|
| Every authenticated route declares the permission it needs. The check runs
| in middleware on the server, so typing a URL directly is refused exactly the
| same way as clicking a hidden link. Hiding the link is a usability decision,
| never a security one (REQ-10.03).
|
*/

// ── Guest ──────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

// ── Authenticated ──────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Password change must stay reachable even when `must_change_password` is
    // set, otherwise the user is trapped in a redirect loop.
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Everything below additionally requires a settled password.
    Route::middleware(['password.settled'])->group(function () {
        // The dashboard is the landing page for every signed-in user; the
        // individual counts inside it are permission-gated per area (§2).
        Route::get('/', DashboardController::class)->name('dashboard');

        // ── Customers (§3) ─────────────────────────────────────────────
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])
                ->middleware('permission:customers.view')->name('index');
            Route::get('/create', [CustomerController::class, 'create'])
                ->middleware('permission:customers.create')->name('create');
            Route::post('/', [CustomerController::class, 'store'])
                ->middleware('permission:customers.create')->name('store');
            Route::get('/{customer}', [CustomerController::class, 'show'])
                ->middleware('permission:customers.view')->name('show');
            Route::get('/{customer}/edit', [CustomerController::class, 'edit'])
                ->middleware('permission:customers.update')->name('edit');
            Route::put('/{customer}', [CustomerController::class, 'update'])
                ->middleware('permission:customers.update')->name('update');
            Route::delete('/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('permission:customers.delete')->name('destroy');
        });

        // ── Contracts (§4) ─────────────────────────────────────────────
        Route::prefix('contracts')->name('contracts.')->group(function () {
            Route::get('/', [ContractController::class, 'index'])
                ->middleware('permission:contracts.view')->name('index');
            Route::get('/create', [ContractController::class, 'create'])
                ->middleware('permission:contracts.create')->name('create');
            Route::post('/', [ContractController::class, 'store'])
                ->middleware('permission:contracts.create')->name('store');
            Route::get('/{contract}', [ContractController::class, 'show'])
                ->middleware('permission:contracts.view')->name('show');
            Route::get('/{contract}/edit', [ContractController::class, 'edit'])
                ->middleware('permission:contracts.update')->name('edit');
            Route::put('/{contract}', [ContractController::class, 'update'])
                ->middleware('permission:contracts.update')->name('update');
            Route::delete('/{contract}', [ContractController::class, 'destroy'])
                ->middleware('permission:contracts.delete')->name('destroy');
        });

        // ── Projects (§5) ──────────────────────────────────────────────
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])
                ->middleware('permission:projects.view')->name('index');
            Route::get('/create', [ProjectController::class, 'create'])
                ->middleware('permission:projects.create')->name('create');
            Route::post('/', [ProjectController::class, 'store'])
                ->middleware('permission:projects.create')->name('store');
            Route::get('/{project}', [ProjectController::class, 'show'])
                ->middleware('permission:projects.view')->name('show');
            Route::get('/{project}/edit', [ProjectController::class, 'edit'])
                ->middleware('permission:projects.update')->name('edit');
            Route::put('/{project}', [ProjectController::class, 'update'])
                ->middleware('permission:projects.update')->name('update');
            Route::delete('/{project}', [ProjectController::class, 'destroy'])
                ->middleware('permission:projects.delete')->name('destroy');
        });

        // ── فاکتورها (OD-41 §19.6) ────────────────────────────────────
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])
                ->middleware('permission:invoices.view')->name('index');
            Route::get('/create', [InvoiceController::class, 'create'])
                ->middleware('permission:invoices.create')->name('create');
            Route::post('/', [InvoiceController::class, 'store'])
                ->middleware('permission:invoices.create')->name('store');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])
                ->middleware('permission:invoices.view')->name('show');
            Route::post('/{invoice}/issue', [InvoiceController::class, 'issue'])
                ->middleware('permission:invoices.issue')->name('issue');
            Route::post('/{invoice}/payments', [InvoiceController::class, 'recordPayment'])
                ->middleware('permission:invoices.record_payment')->name('payments.store');
        });

        // ── حسابداری (OD-41) ──────────────────────────────────────────
        // Every financial route is sensitive. `accounting.view` gates reading,
        // `post` writing, and close/reopen separately (§19.2).
        Route::prefix('accounting')->name('accounting.')->group(function () {
            Route::get('/chart', [AccountingController::class, 'chart'])
                ->middleware('permission:accounting.view')->name('chart');
            Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])
                ->middleware('permission:accounting.view')->name('trial-balance');
            Route::get('/profit-loss', [AccountingController::class, 'profitLoss'])
                ->middleware('permission:accounting.view')->name('profit-loss');
            Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet'])
                ->middleware('permission:accounting.view')->name('balance-sheet');
            Route::get('/journal', [AccountingController::class, 'journal'])
                ->middleware('permission:accounting.view')->name('journal');
            Route::get('/journal/{entry}', [AccountingController::class, 'journalShow'])
                ->middleware('permission:accounting.view')->name('journal.show');
            Route::get('/payments', [AccountingController::class, 'payments'])
                ->middleware('permission:accounting.view')->name('payments');

            Route::get('/periods', [AccountingController::class, 'periods'])
                ->middleware('permission:accounting.view')->name('periods');
            Route::post('/periods/{period}/close', [AccountingController::class, 'closePeriod'])
                ->middleware('permission:accounting.close')->name('periods.close');
            Route::post('/periods/{period}/reopen', [AccountingController::class, 'reopenPeriod'])
                ->middleware('permission:accounting.reopen')->name('periods.reopen');

            Route::post('/invoices/{invoice}/void', [AccountingController::class, 'voidInvoice'])
                ->middleware('permission:invoices.void')->name('invoices.void');
        });

        // ── کارتابل (EXP-WORKITEM-001) ───────────────────────────────
        // Reading the cartable is ordinary operational awareness; closing an
        // item is a separate grant because it is what records revenue.
        Route::prefix('work-items')->name('work-items.')->group(function () {
            Route::get('/', [WorkItemController::class, 'index'])
                ->middleware('permission:work_items.view')->name('index');
            Route::post('/', [WorkItemController::class, 'store'])
                ->middleware('permission:work_items.create')->name('store');
            Route::get('/{workItem}', [WorkItemController::class, 'show'])
                ->middleware('permission:work_items.view')->name('show');
            Route::post('/{workItem}/complete', [WorkItemController::class, 'complete'])
                ->middleware('permission:work_items.complete')->name('complete');
            Route::post('/{workItem}/reopen', [WorkItemController::class, 'reopen'])
                ->middleware('permission:work_items.complete')->name('reopen');
            Route::post('/{workItem}/cancel', [WorkItemController::class, 'cancel'])
                ->middleware('permission:work_items.cancel')->name('cancel');
        });

        // ── یادداشت و وظیفه (EXP-NOTE-001) ───────────────────────────
        Route::prefix('notes')->name('notes.')->group(function () {
            Route::get('/', [NoteController::class, 'index'])
                ->middleware('permission:notes.view')->name('index');
            Route::get('/create', [NoteController::class, 'create'])
                ->middleware('permission:notes.create')->name('create');
            Route::post('/', [NoteController::class, 'store'])
                ->middleware('permission:notes.create')->name('store');
            Route::get('/{note}/edit', [NoteController::class, 'edit'])
                ->middleware('permission:notes.update')->name('edit');
            Route::put('/{note}', [NoteController::class, 'update'])
                ->middleware('permission:notes.update')->name('update');
            Route::post('/{note}/complete', [NoteController::class, 'complete'])
                ->middleware('permission:notes.update')->name('complete');
            Route::post('/{note}/reopen', [NoteController::class, 'reopen'])
                ->middleware('permission:notes.update')->name('reopen');
            Route::delete('/{note}', [NoteController::class, 'destroy'])
                ->middleware('permission:notes.delete')->name('destroy');
        });

        // ── Project credentials (§6, REQ-06.01/03/04) ──────────────────
        // Three distinct permissions rather than one. Seeing the list is
        // `view`, writing a secret is `manage`, and reading one back is
        // `reveal` — a separate, audited, sensitive action.
        Route::prefix('projects/{project}/credentials')->name('credentials.')->group(function () {
            Route::get('/', [CredentialController::class, 'index'])
                ->middleware('permission:credentials.view')->name('index');
            Route::get('/create', [CredentialController::class, 'create'])
                ->middleware('permission:credentials.manage')->name('create');
            Route::post('/', [CredentialController::class, 'store'])
                ->middleware('permission:credentials.manage')->name('store');
            Route::get('/{credential}/edit', [CredentialController::class, 'edit'])
                ->middleware('permission:credentials.manage')->name('edit');
            Route::put('/{credential}', [CredentialController::class, 'update'])
                ->middleware('permission:credentials.manage')->name('update');
            Route::delete('/{credential}', [CredentialController::class, 'destroy'])
                ->middleware('permission:credentials.manage')->name('destroy');

            // POST, never GET: a secret must not appear in a URL, a browser
            // history entry or a proxy log (REQ-06.04).
            Route::post('/{credential}/reveal', [CredentialController::class, 'reveal'])
                ->middleware('permission:credentials.reveal')->name('reveal');
        });
    });
});
