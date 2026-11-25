<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * OD-41 — one row of the chart of accounts.
 *
 * The tree is stored as `parent_id` + a stored `level`. `level` is denormalised
 * on purpose: "may this account receive a journal line" is asked on every single
 * posting, and deriving it by walking ancestors would turn one insert into N
 * queries. It is written once, at seed/creation time.
 */
class LedgerAccount extends Model
{
    protected $fillable = [
        'code', 'title', 'parent_id', 'level', 'type', 'normal_balance',
        'is_postable', 'is_active', 'is_system', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'level' => 'integer',
        ];
    }

    public const TYPES = [
        'asset' => 'دارایی',
        'liability' => 'بدهی',
        'equity' => 'حقوق مالکانه',
        'revenue' => 'درآمد',
        'expense' => 'هزینه',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Balance in the account's own normal direction.
     *
     * A revenue account with 100 credited and 20 debited has a balance of 80,
     * not -80. Sign-flipping here keeps the trial balance readable: every
     * positive number in the report means "as expected for this account type".
     */
    public function balance(): int
    {
        $sums = $this->lines()
            ->selectRaw('COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c')
            ->first();

        $debit = (int) ($sums->d ?? 0);
        $credit = (int) ($sums->c ?? 0);

        return $this->normal_balance === 'debit' ? $debit - $credit : $credit - $debit;
    }

    public function scopePostable($query)
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    /** Full title path, e.g. «دارایی‌ها › دارایی جاری › بانک و صندوق». */
    public function pathLabel(): string
    {
        $parts = [$this->title];
        $node = $this;
        $guard = 0;

        while ($node->parent !== null && $guard < 8) {
            $node = $node->parent;
            array_unshift($parts, $node->title);
            $guard++;
        }

        return implode(' › ', $parts);
    }
}
