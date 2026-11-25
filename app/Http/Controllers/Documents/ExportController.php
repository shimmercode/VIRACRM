<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Payslip;
use App\Models\Project;
use App\Models\ProjectCredential;
use App\Services\Pdf\ContractPdf;
use App\Services\Pdf\CredentialPdf;
use App\Services\Pdf\PayslipPdf;
use App\Services\Pdf\ProjectPdf;
use App\Services\Security\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * REQ-09.05 — the four PDF exports.
 *
 * ONE PLACE THAT KNOWS HOW TO HAND A PDF TO A BROWSER
 * ---------------------------------------------------
 * Every export below returns the same way: bytes in the body, `application/pdf`,
 * `Content-Disposition`, and `Cache-Control: no-store, private`. Doing that here
 * rather than in four controllers means a new export cannot quietly omit the
 * no-store header, which is the difference between a confidential document and
 * one sitting in a shared machine's disk cache.
 *
 * WHY THESE ARE POST FOR THE CONFIDENTIAL ONE
 * -------------------------------------------
 * The credentials PDF is POST. Every other export is GET because it is a view of
 * data the user may already see, and a GET lets a link work from a menu. The
 * credentials document is a different act: it concentrates every secret on a
 * project into one portable file, so it is POST (no URL in history), separately
 * permissioned (`credentials.export`), and audited.
 *
 * WHY NOTHING IS WRITTEN TO DISK
 * ------------------------------
 * A PDF in `public/` is the file nobody remembers to protect, and `storage/app`
 * needs a cleanup job nobody writes. The bytes go straight to the response.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ContractPdf $contracts,
        private readonly PayslipPdf $payslips,
        private readonly ProjectPdf $projects,
        private readonly CredentialPdf $credentials,
    ) {
    }

    /** T-PDF-01 — a finalised contract. */
    public function contract(Request $request, Contract $contract): Response
    {
        try {
            $bytes = $this->contracts->build($contract);
        } catch (RuntimeException $e) {
            /* The builder refuses a draft. Sending the user back with the
             * reason is right: a 500 would lose the message and look like a
             * bug rather than a rule. */
            return $this->refuse($e->getMessage());
        }

        return $this->pdf($bytes, $this->contracts->filename($contract));
    }

    /** T-PDF-10 — one payslip. */
    public function payslip(Request $request, Payslip $payslip): Response
    {
        return $this->pdf($this->payslips->build($payslip), $this->payslips->filename($payslip));
    }

    /** T-PDF-08 — the PUBLIC project document; contains no credentials. */
    public function project(Request $request, Project $project): Response
    {
        return $this->pdf($this->projects->build($project), $this->projects->filename($project));
    }

    /** T-PDF-09 — the CONFIDENTIAL credentials document. */
    public function credentials(Request $request, Project $project): Response
    {
        $credentials = ProjectCredential::query()
            ->where('project_id', $project->id)
            ->with('type')
            ->orderBy('label')
            ->get();

        if (! CredentialPdf::hasContent($credentials)) {
            return $this->refuse('این پروژه هیچ دسترسی ثبت‌شده‌ای ندارد؛ سند محرمانه‌ای برای تولید وجود ندارد.');
        }

        $bytes = $this->credentials->build($project, $credentials, (string) $request->user()?->name);

        /* §6 — exporting confidential material must be attributable. The audit
         * row is written AFTER the document is built: if rendering failed there
         * is nothing to attribute, and a row saying "exported" for a file that
         * was never produced would be a false record. */
        AuditRecorder::exported(
            $project,
            'credentials-pdf',
            'خروجی PDF محرمانهٔ دسترسی‌ها: ' . $project->title . ' (' . $credentials->count() . ' مورد)',
        );

        return $this->pdf($bytes, $this->credentials->filename($project));
    }

    /**
     * The one response shape for a PDF.
     *
     * `no-store` matters on every one of these — a payslip and a contract are
     * personal documents, and `private` keeps them out of shared caches too.
     */
    private function pdf(string $bytes, string $filename): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** A refusal is a redirect with a reason, never an empty PDF. */
    private function refuse(string $message): Response
    {
        return response()->view('errors.pdf-unavailable', ['message' => $message], 409, [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
