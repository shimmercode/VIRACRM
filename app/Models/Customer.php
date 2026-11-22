<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REQ-03.01–03.08 — the customer record.
 *
 * §1 demands "یک‌بار ثبت اطلاعات مشتری" (enter customer data once) and §3
 * forbids duplicates. Two things make that real rather than aspirational:
 *
 *   1. `search_key` / `normalized_mobile` / `normalized_national_id` are
 *      maintained automatically, so lookups match across Persian digits,
 *      spaces, dashes and country-code prefixes.
 *   2. `duplicateCandidates()` returns an explainable list — the UI must show
 *      *why* two records look like the same company, not just block the save.
 *
 * Blocking on a fuzzy match would annoy users into entering "شرکت آریا ۲",
 * which is how duplicate databases are born. Showing candidates and letting a
 * human decide is the behaviour that actually prevents duplicates.
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'type',
        'first_name', 'last_name', 'father_name', 'national_id', 'birth_date',
        'company_name', 'registration_number', 'economic_code', 'legal_representative',
        'mobile', 'phone', 'email', 'address', 'postal_code', 'city', 'province',
        'acquaintance_source', 'notes', 'status', 'owner_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'birth_date' => 'date',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function websites(): HasMany
    {
        return $this->hasMany(CustomerWebsite::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * §7: notes attached to this customer.
     *
     * NAMED `noteEntries` ON PURPOSE. The `customers` table also has a TEXT
     * column called `notes` (§3, the free-text profile note). A relation named
     * `notes()` shadows that column on every read, so `$customer->notes` would
     * silently return a Collection where a string is expected. Renaming the
     * relation is the only fix that keeps both meanings available.
     */
    public function noteEntries(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    // ── Derived values ───────────────────────────────────────────────────

    /**
     * The name a human uses in a list, regardless of type.
     *
     * Method name `displayName` (not `displayNameAttribute`): Laravel maps a
     * snake_case attribute to a camelCase method, so `$customer->display_name`
     * needs `displayName()`. A method ending in `Attribute` maps to the literal
     * attribute `display_name_attribute` and would silently return null.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->type === CustomerType::Legal) {
                return (string) ($this->company_name ?: 'شرکت بدون نام');
            }

            $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

            return $name !== '' ? $name : 'مشتری بدون نام';
        });
    }

    /** Primary domain if any — §3 requires search by domain. */
    protected function primaryDomain(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->websites
            ->sortByDesc('is_primary')
            ->first()?->domain);
    }

    // ── Normalisation (dedup support) ────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (self $customer): void {
            // Arabic yeh/kaf and Arabic-Indic digits are keyboard artefacts in
            // Persian text, never intent. Normalising them on save keeps lists,
            // PDFs and search consistent. ZWNJ is preserved because it is
            // meaningful Persian orthography (نیم‌فاصله).
            foreach (['first_name', 'last_name', 'company_name', 'legal_representative'] as $field) {
                if (filled($customer->{$field})) {
                    $customer->{$field} = self::foldArabicLetters((string) $customer->{$field});
                }
            }

            // Identity: a natural person is identified by national ID, a legal
            // entity by its registration number (§3). Both share one indexed
            // column so duplicate detection stays a single query.
            $identity = $customer->type === CustomerType::Legal
                ? $customer->registration_number
                : $customer->national_id;

            // forceFill: these are derived columns, deliberately not one of the
            // mass-assignable fields.
            $customer->forceFill([
                'normalized_mobile' => self::normalizeMobile($customer->mobile ?: $customer->phone),
                'normalized_national_id' => self::normalizeDigits($identity),
                'search_key' => $customer->buildSearchKey(),
            ]);
        });
    }

    /**
     * Strip everything that varies between honest data-entry styles.
     * "۰۹۱۲ ۱۲۳ ۴۵۶۷" / "0912-1234567" / "+98 912 123 4567" all become
     * "09121234567".
     */
    public static function normalizeMobile(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', \App\Support\Money::toAsciiDigits($value));

        if ($digits === null || $digits === '') {
            return null;
        }

        // Drop an international prefix so +98… and 0… compare equal.
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (str_starts_with($digits, '0098')) {
            $digits = '0' . substr($digits, 4);
        }

        return $digits;
    }

    public static function normalizeDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', Money::toAsciiDigits($value));

        return $digits === '' ? null : $digits;
    }

    /**
     * A comparable key for the human-facing name.
     * Persian text is normalised for Arabic-vs-Persian yeh/kaf and ZWNJ so
     * "شركت آريا" and "شرکت آریا" collide as intended.
     */
    public function buildSearchKey(): ?string
    {
        $parts = [];

        if ($this->type === CustomerType::Legal) {
            $parts[] = $this->company_name;
            $parts[] = $this->registration_number;
        } else {
            $parts[] = $this->first_name;
            $parts[] = $this->last_name;
            $parts[] = $this->national_id;
        }


        $parts[] = $this->mobile;
        $parts[] = $this->email;

        $joined = implode(' ', array_filter(array_map('strval', $parts), fn ($p) => trim($p) !== ''));

        return $joined === '' ? null : self::normalizePersianText($joined);
    }

    /**
     * Fold Arabic letter variants and Arabic-Indic digits, preserving ZWNJ and
     * spacing. Safe to apply to values that will be displayed.
     */
    public static function foldArabicLetters(string $value): string
    {
        $value = Money::toAsciiDigits($value);

        return str_replace(
            ['ي', 'ك', 'ة', 'ۀ', 'ؤ', 'إ', 'أ', 'ٱ'],
            ['ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', 'ا'],
            $value
        );
    }

    /**
     * Persian text normaliser for comparison: fold letters, drop ZWNJ and
     * diacritics, collapse whitespace, lowercase. Used for search keys only.
     */
    public static function normalizePersianText(string $value): string
    {
        $value = Money::toAsciiDigits($value);

        $value = str_replace(
            ['ي', 'ك', 'ة', 'ۀ', 'ؤ', 'إ', 'أ', 'ٱ', 'ـ'],
            ['ی', 'ک', 'ه', 'ه', 'و', 'ا', 'ا', 'ا', ''],
            $value
        );

        // Remove ZWNJ and any remaining invisible marks.
        $value = str_replace(["\u{200c}", "\u{200d}", "\u{200f}", "\u{200e}"], ' ', $value);

        // Drop Arabic diacritics.
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value);

        return trim($value);
    }

    // ── Duplicate detection ──────────────────────────────────────────────

    /**
     * Find records that might be the same customer.
     *
     * Returns an array of ['customer' => Customer, 'reasons' => list<string>]
     * so the UI can explain the match. Ordered strongest signal first.
     *
     * @return list<array{customer: self, reasons: list<string>}>
     */
    public static function duplicateCandidates(self $candidate, int $limit = 5): array
    {
        $found = [];

        // An unsaved model has not run the saving hook yet, so the derived
        // columns are still null. Compute them locally rather than requiring
        // every caller to remember to persist first.
        $candidateMobile = $candidate->normalized_mobile
            ?: self::normalizeMobile($candidate->mobile ?: $candidate->phone);
        $candidateIdentity = $candidate->normalized_national_id
            ?: self::normalizeDigits(
                $candidate->type === CustomerType::Legal
                    ? $candidate->registration_number
                    : $candidate->national_id
            );

        $add = function (?self $match, string $reason) use (&$found, $candidate): void {
            if ($match === null || ($candidate->exists && $match->id === $candidate->id)) {
                return;
            }
            $found[$match->id]['customer'] = $match;
            $found[$match->id]['reasons'][] = $reason;
        };

        if ($candidateIdentity) {
            self::query()
                ->where('normalized_national_id', $candidateIdentity)
                ->limit($limit)
                ->get()
                ->each(fn ($m) => $add($m, 'کد ملی / شناسه ملی یکسان'));
        }

        if ($candidateMobile) {
            self::query()
                ->where('normalized_mobile', $candidateMobile)
                ->limit($limit)
                ->get()
                ->each(fn ($m) => $add($m, 'شماره تماس یکسان'));
        }

        if ($candidate->type === CustomerType::Legal && filled($candidate->registration_number)) {
            self::query()
                ->where('registration_number', $candidate->registration_number)
                ->limit($limit)
                ->get()
                ->each(fn ($m) => $add($m, 'شماره ثبت یکسان'));
        }

        // Name similarity: compare only the human name, folded. The full
        // search_key also contains the phone and identity, so matching on it
        // would fire "same name" for records that merely share a number.
        $nameOnly = self::normalizePersianText(
            $candidate->type === CustomerType::Legal
                ? (string) $candidate->company_name
                : trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? ''))
        );

        if ($nameOnly !== '') {
            self::query()
                ->whereNotNull('search_key')
                ->where('search_key', 'like', '%' . self::escapeLike($nameOnly) . '%')
                ->limit($limit)
                ->get()
                ->each(fn ($m) => $add($m, 'نام مشابه'));
        }

        // Domain overlap: two records claiming the same website are almost
        // certainly the same company (§3 search-by-domain requirement).
        foreach ($candidate->websites as $site) {
            if (! $site->domain) {
                continue;
            }
            $siblings = CustomerWebsite::query()
                ->where('domain', $site->domain)
                ->where('customer_id', '!=', $candidate->id ?? 0)
                ->with('customer')
                ->limit($limit)
                ->get();

            foreach ($siblings as $sibling) {
                if ($sibling->customer) {
                    $add($sibling->customer, 'دامنه یکسان: ' . $site->domain);
                }
            }
        }

        return array_values($found);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** §3: search across name, phone, national ID and domain. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $normalized = self::normalizePersianText($term);
        $digits = self::normalizeMobile($term);

        if ($normalized === '' && $digits === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($normalized, $digits, $term): void {
            if ($normalized !== '') {
                $q->where('search_key', 'like', '%' . self::escapeLike($normalized) . '%')
                    ->orWhere('first_name', 'like', '%' . $term . '%')
                    ->orWhere('last_name', 'like', '%' . $term . '%')
                    ->orWhere('company_name', 'like', '%' . $term . '%')
                    ->orWhereHas('websites', fn (Builder $w) => $w->where('domain', 'like', '%' . $normalized . '%'));
            }

            if ($digits !== null && $digits !== '') {
                $q->orWhere('normalized_mobile', 'like', '%' . $digits . '%')
                    ->orWhere('normalized_national_id', 'like', '%' . $digits . '%');
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function amountOfActiveContracts(): Money
    {
        return Money::fromRial((int) $this->contracts()->whereIn('status', ['active', 'expiring'])->sum('amount'));
    }
}
