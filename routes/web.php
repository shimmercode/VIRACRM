<?php

use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Settings\SettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Contracts\ContractTemplateController;
use App\Http\Controllers\Credentials\CredentialController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\PayrollController;
use App\Http\Controllers\Notes\NoteController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\WorkItems\WorkItemController;
use App\Http\Controllers\Documents\ExportController;
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

        /*
         * ── Audit trail (§10) ──────────────────────────────────────────
         *
         * ONE route, and it reads. There is deliberately no POST, PUT, PATCH or
         * DELETE here: an audit log an administrator can amend is not evidence.
         * The absence is enforced by AuditLogPageTest, which asserts those verbs
         * are refused, so a later "quick fix" that adds one fails a test.
         *
         * `audit.view` is granted to no seeded role, which means in practice
         * only a superadmin reaches this page — the log names who did what, so
         * the people who appear in it must not be the ones reading it.
         */
        Route::get('/audit', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view')->name('audit.index');

        /*
         * §15 — configurable settings.
         *
         * View and manage are separate permissions: reading a threshold and
         * moving it are different powers. The update route is POST, not PUT, so
         * the whole form is one submission and the CSRF token covers every
         * field at once.
         *
         * There is deliberately no store or destroy here. The set of settings is
         * defined by the seeder, not by the user: an endpoint that accepts
         * arbitrary keys is how a typo becomes a permanent orphan row.
         */
        Route::get('/settings', [SettingController::class, 'index'])
            ->middleware('permission:settings.view')->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])
            ->middleware('permission:settings.manage')->name('settings.update');

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
            /*
             * TEMPLATES MUST BE REGISTERED BEFORE THE `{contract}` WILDCARD.
             *
             * Laravel matches routes in registration order. With `/{contract}`
             * first, `GET /contracts/templates` binds "templates" as a contract
             * id and returns 404 — the page exists, the link is in the sidebar,
             * and it never loads. This is the classic ordering trap and it is
             * noted here because the correct order looks arbitrary otherwise.
             */
            // REQ-04.04 — templates. Senior to the contract itself: a template is
            // the wording every future contract inherits.
            Route::get('/templates', [ContractTemplateController::class, 'index'])
                ->middleware('permission:contracts.finalize')->name('templates.index');
            Route::get('/templates/create', [ContractTemplateController::class, 'create'])
                ->middleware('permission:contracts.finalize')->name('templates.create');
            Route::post('/templates', [ContractTemplateController::class, 'store'])
                ->middleware('permission:contracts.finalize')->name('templates.store');
            Route::get('/templates/{template}/edit', [ContractTemplateController::class, 'edit'])
                ->middleware('permission:contracts.finalize')->name('templates.edit');
            Route::put('/templates/{template}', [ContractTemplateController::class, 'update'])
                ->middleware('permission:contracts.finalize')->name('templates.update');
            Route::delete('/templates/{template}', [ContractTemplateController::class, 'destroy'])
                ->middleware('permission:contracts.finalize')->name('templates.destroy');

            // REQ-09.05 / T-PDF-01 — the printable contract. Declared before
            // `GET /{contract}` for the same reason /templates is: a static
            // segment registered after a wildcard is swallowed by it, so
            // /contracts/12/pdf would look up id "pdf".
            //
            // `contracts.view` is enough to read the document, but the amount is
            // rendered inside it, so the financial permission is required too.
            Route::get('/{contract}/pdf', [ExportController::class, 'contract'])
                ->middleware('permission:contracts.view')
                ->middleware('permission:contracts.view_financials')
                ->name('pdf');

            Route::get('/{contract}', [ContractController::class, 'show'])
                ->middleware('permission:contracts.view')->name('show');
            Route::get('/{contract}/edit', [ContractController::class, 'edit'])
                ->middleware('permission:contracts.update')->name('edit');
            Route::put('/{contract}', [ContractController::class, 'update'])
                ->middleware('permission:contracts.update')->name('update');
            Route::delete('/{contract}', [ContractController::class, 'destroy'])
                ->middleware('permission:contracts.delete')->name('destroy');

            // REQ-04.05 — finalising renders the template, assigns the permanent
            // number and snapshots the contract. Its own permission: an account
            // manager may prepare a contract, but committing it to the client is
            // a different act.
            Route::post('/{contract}/finalize', [ContractController::class, 'finalize'])
                ->middleware('permission:contracts.finalize')->name('finalize');
            Route::post('/{contract}/renew', [ContractController::class, 'renew'])
                ->middleware('permission:contracts.finalize')->name('renew');


        });

        // ── Projects (§5) ──────────────────────────────────────────────
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [ProjectController::class, 'index'])
                ->middleware('permission:projects.view')->name('index');
            Route::get('/create', [ProjectController::class, 'create'])
                ->middleware('permission:projects.create')->name('create');
            Route::post('/', [ProjectController::class, 'store'])
                ->middleware('permission:projects.create')->name('store');
            // REQ-06.06 / T-PDF-08 — the PUBLIC project document. Declared
            // before `GET /{project}` so "pdf" is not read as a project id.
            Route::get('/{project}/pdf', [ExportController::class, 'project'])
                ->middleware('permission:projects.view')->name('pdf');

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

        // ── ساختار سازمانی (§17 / §19.3) ─────────────────────────────
        // One screen for departments and their cost centres. Editing the
        // structure is an administrative act, deliberately NOT implied by
        // `accounting.post`: an accountant posts against the dimensions, a
        // manager restructures them.
        Route::prefix('organization')->name('organization.')->group(function () {
            Route::get('/', [DepartmentController::class, 'index'])
                ->middleware('permission:departments.view')->name('index');
            Route::post('/departments', [DepartmentController::class, 'store'])
                ->middleware('permission:departments.manage')->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])
                ->middleware('permission:departments.manage')->name('departments.update');
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
                ->middleware('permission:departments.delete')->name('departments.destroy');
            Route::post('/cost-centers', [DepartmentController::class, 'storeCostCenter'])
                ->middleware('permission:departments.manage')->name('cost-centers.store');
            Route::delete('/cost-centers/{costCenter}', [DepartmentController::class, 'destroyCostCenter'])
                ->middleware('permission:departments.delete')->name('cost-centers.destroy');
        });

        // ── پرسنل (§15 / REQ-07) ─────────────────────────────────────
        // Personal data, so the whole module sits behind `personnel.view`.
        // Deleting an employee is a separate grant: it archives a personnel
        // file, which HR owns but a project manager does not.
        Route::prefix('employees')->name('employees.')->group(function () {
            Route::get('/', [EmployeeController::class, 'index'])
                ->middleware('permission:personnel.view')->name('index');
            Route::get('/create', [EmployeeController::class, 'create'])
                ->middleware('permission:personnel.create')->name('create');
            Route::post('/', [EmployeeController::class, 'store'])
                ->middleware('permission:personnel.create')->name('store');
            Route::get('/{employee}', [EmployeeController::class, 'show'])
                ->middleware('permission:personnel.view')->name('show');
            Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])
                ->middleware('permission:personnel.update')->name('edit');
            Route::put('/{employee}', [EmployeeController::class, 'update'])
                ->middleware('permission:personnel.update')->name('update');
            Route::delete('/{employee}', [EmployeeController::class, 'destroy'])
                ->middleware('permission:personnel.delete')->name('destroy');
        });

        // ── حقوق و دستمزد (OD-16) ─────────────────────────────────────
        // Every payroll route carries a SENSITIVE permission (the catalogue
        // marks payroll.* sensitive) because a payslip is personal financial
        // data. Publishing is gated separately from editing: the accountant
        // prepares, the approver commits it to the ledger.
        Route::prefix('payroll')->name('payroll.')->group(function () {
            Route::get('/', [PayrollController::class, 'index'])
                ->middleware('permission:payroll.view')->name('index');
            Route::post('/periods', [PayrollController::class, 'storePeriod'])
                ->middleware('permission:payroll.manage')->name('periods.store');
            Route::get('/periods/{period}', [PayrollController::class, 'show'])
                ->middleware('permission:payroll.view')->name('show');
            Route::post('/periods/{period}/build', [PayrollController::class, 'build'])
                ->middleware('permission:payroll.manage')->name('build');
            Route::post('/periods/{period}/compute', [PayrollController::class, 'compute'])
                ->middleware('permission:payroll.manage')->name('compute');
            Route::post('/periods/{period}/publish', [PayrollController::class, 'publish'])
                ->middleware('permission:payroll.approve')->name('publish');

            Route::get('/payslips/{payslip}', [PayrollController::class, 'payslip'])
                ->middleware('permission:payroll.view')->name('payslip');
            // REQ-07.03 / T-PDF-10 — the printable payslip.
            Route::get('/payslips/{payslip}/pdf', [ExportController::class, 'payslip'])
                ->middleware('permission:payroll.view')->name('payslip.pdf');
            Route::put('/payslips/{payslip}', [PayrollController::class, 'updatePayslip'])
                ->middleware('permission:payroll.manage')->name('payslip.update');
            Route::post('/payslips/{payslip}/items', [PayrollController::class, 'storeItem'])
                ->middleware('permission:payroll.manage')->name('items.store');
            Route::delete('/items/{item}', [PayrollController::class, 'destroyItem'])
                ->middleware('permission:payroll.manage')->name('items.destroy');
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

            /*
             * REQ-06.05 / T-PDF-09 — the confidential document.
             *
             * POST for the same reason reveal is: it concentrates every secret
             * on the project into one portable file. A GET would put that act in
             * browser history, proxy logs and any Referer header, and would let
             * a link in a chat app trigger it. `credentials.export` is separate
             * from `view` and `reveal` because "may see there is a login" and
             * "may carry all the logins away in a PDF" are different powers.
             */
            Route::post('/export', [ExportController::class, 'credentials'])
                ->middleware('permission:credentials.export')->name('export');
        });
    });
});
