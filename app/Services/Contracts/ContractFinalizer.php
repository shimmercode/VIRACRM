<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * REQ-04.05–04.08 — finalising a contract and renewing it.
 *
 * WHAT "FINALISE" MEANS, AND WHY IT IS NOT JUST A STATUS CHANGE
 * ------------------------------------------------------------
 * A contract that has been shown to a client is a document, not a draft. Three
 * things must become true at that moment and stay true:
 *
 *   1. the body is rendered from the template and stored, so a later template
 *      edit cannot retroactively change what the client signed;
 *   2. the contract gets a permanent number, because a document with no number
 *      cannot be referenced in an invoice or a dispute;
 *   3. a version row holds the whole contract as it was, so a later amendment
 *      can be read against it.
 *
 * Anything that only flips `status` to "active" fails all three and looks
 * identical in a screenshot — which is exactly why this lives in a service and
 * is proved by a test that reads the stored version row.
 */
final class ContractFinalizer
{
    public function __construct(private readonly ContractRenderer $renderer)
    {
    }

    /**
     * Finalise: render, number, snapshot, activate.
     *
     * @throws RuntimeException when the contract is already finalised or the
     *                          template body cannot be rendered.
     */
    public function finalize(Contract $contract, User $actor, ?ContractTemplate $template = null, ?string $reason = null): Contract
    {
        if ($contract->finalized_at !== null) {
            throw new RuntimeException('این قرارداد قبلاً نهایی شده است.');
        }

        $template ??= $this->defaultTemplateFor($contract);

        return DB::transaction(function () use ($contract, $actor, $template, $reason) {
            $rendered = null;

            if ($template !== null) {
                $rendered = $this->renderer->render((string) $template->body, $contract);
            } elseif (trim((string) $contract->description) !== '') {
                // No template: the description IS the document. Better than
                // storing an empty body and calling it finalised.
                $rendered = (string) $contract->description;
            } else {
                throw new RuntimeException(
                    'برای نهایی‌سازی، یک قالب قرارداد انتخاب کنید یا توضیحات قرارداد را پر کنید. '
                    . 'بدون متن، سند قابل استناد وجود ندارد.'
                );
            }

            if ($contract->number === null || $contract->number === '') {
                $contract->number = $this->nextNumber($contract);
            }

            // `status` is cast to the ContractStatus enum. Assigning the enum
            // (not the raw string) keeps the in-memory model consistent with the
            // row — assigning a string leaves an enum-typed attribute holding a
            // string until the next refresh, and anything that casts it back
            // (audit diff, blade) then throws "could not be converted to string".
            $contract->forceFill([
                'rendered_body' => $rendered,
                'template_id' => $template?->id,
                'finalized_at' => now(),
                'finalized_by' => $actor->id,
                'status' => \App\Enums\ContractStatus::Active,
            ])->save();

            $this->snapshot($contract, $actor, $reason ?? 'نسخهٔ نهایی‌شده');

            return $contract->refresh();
        });
    }

    /**
     * Renew: a NEW contract that points back at the old one.
     *
     * The old contract is never edited — it is what the parties performed under
     * for its whole term, and rewriting its dates would erase that. The renewal
     * copies the terms, takes a new number, and is left in draft so someone
     * confirms the new amount before it becomes an obligation.
     */
    public function renew(Contract $contract, User $actor, array $changes, string $reason): Contract
    {
        if ($contract->finalized_at === null) {
            throw new RuntimeException('قرارداد نهایی‌نشده قابل تمدید نیست.');
        }

        return DB::transaction(function () use ($contract, $actor, $changes, $reason) {
            $renewal = $contract->replicate([
                // Identity, numbering and finalisation do not carry over.
                'number', 'rendered_body', 'finalized_at', 'finalized_by',
                'renewed_from_id',
            ]);

            $renewal->renewed_from_id = $contract->id;
            $renewal->status = \App\Enums\ContractStatus::Draft;
            $renewal->finalized_at = null;
            $renewal->finalized_by = null;
            $renewal->rendered_body = null;
            $renewal->number = $this->nextNumber($contract);

            $renewal->fill(array_intersect_key($changes, array_flip([
                'title', 'amount', 'start_date', 'end_date', 'description',
                'party_one_name', 'party_two_name', 'party_one_address',
                'party_two_address', 'internal_notes',
            ])));

            $renewal->save();

            // The old contract keeps its body and its number; only its status
            // moves, and a version row records why.
            $contract->forceFill(['status' => \App\Enums\ContractStatus::Renewed])->save();
            $this->snapshot($contract, $actor, $reason . ' — تمدید و ایجاد قرارداد ' . $renewal->number);

            return $renewal;
        });
    }

    /** Amend the body of a finalised contract, keeping the previous one. */
    public function amendBody(Contract $contract, string $body, string $reason, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $body, $reason, $actor) {
            $this->snapshot($contract, $actor, $reason);

            $contract->forceFill(['rendered_body' => $body])->save();

            return $contract->refresh();
        });
    }

    private function snapshot(Contract $contract, User $actor, string $reason): ContractVersion
    {
        $next = (int) ContractVersion::query()->where('contract_id', $contract->id)->max('version') + 1;

        return ContractVersion::create([
            'contract_id' => $contract->id,
            'version' => $next,
            'body' => (string) ($contract->rendered_body ?? ''),
            'snapshot' => $contract->toArray(),
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
    }

    private function defaultTemplateFor(Contract $contract): ?ContractTemplate
    {
        return ContractTemplate::query()
            ->where('is_active', true)
            ->when($contract->contract_type_id !== null, fn ($q) => $q->where('contract_type_id', $contract->contract_type_id))
            ->orderByDesc('is_default')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Next contract number: VIRA-C-1405-0001.
     *
     * Scoped by the Jalali year of the START date, because a contract is filed
     * by the year it begins, and numbered from the max existing number rather
     * than the row count — a soft delete must not make two contracts share a
     * number.
     */
    /**
     * Public so contract creation and finalisation share ONE numbering scheme.
     *
     * Two schemes is the kind of bug that only shows up in a document: the create
     * form produced `VIRA-C-0008` while finalisation produced `VIRA-C-1405-0001`,
     * so a contract changed its number meaning when it was finalised — or kept a
     * non-year-scoped one, depending on which path assigned it first.
     */
    public function nextNumber(Contract $contract): string
    {
        $year = \App\Support\JalaliDate::fromCarbon(
            \Carbon\CarbonImmutable::parse($contract->start_date ?? now())
        )->year;

        $prefix = 'VIRA-C-' . $year . '-';
        $max = Contract::withTrashed()
            ->where('number', 'like', $prefix . '%')
            ->pluck('number')
            ->map(fn (string $n) => (int) substr($n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
