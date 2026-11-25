<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Contract extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'currency' => 'PKR',
        'status' => 'draft',
        'apply_sales_tax' => true,
        'apply_wht' => true,
        'late_fee_percent' => 0,
        'renewal_increase_percent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'billing_cycle' => BillingCycle::class,
            'agreement_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'fee' => 'decimal:2',
            'late_fee_percent' => 'decimal:2',
            'renewal_increase_percent' => 'decimal:2',
            'sales_tax_rate' => 'decimal:2',
            'wht_rate' => 'decimal:2',
            'apply_sales_tax' => 'boolean',
            'apply_wht' => 'boolean',
            'field_values' => 'array',
            'clause_flags' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'signed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class)->withTrashed(); }
    public function template(): BelongsTo { return $this->belongsTo(Template::class); }
    public function templateVersion(): BelongsTo { return $this->belongsTo(TemplateVersion::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function parent(): BelongsTo { return $this->belongsTo(Contract::class, 'parent_contract_id'); }
    public function documents(): HasMany { return $this->hasMany(ContractDocument::class)->latest(); }
    public function signingRequests(): HasMany { return $this->hasMany(SigningRequest::class)->latest('id'); }

    /** The current open signing link, if one has been sent and is not yet signed/voided/expired. */
    public function activeSigningRequest(): ?SigningRequest
    {
        return $this->signingRequests()
            ->whereIn('status', [\App\Enums\SigningStatus::Pending->value, \App\Enums\SigningStatus::Verified->value])
            ->get()
            ->first(fn (SigningRequest $r) => ! $r->isExpired());
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_id')->where('subject_type', static::class)->latest('created_at');
    }

    /** End date = day before the same date N months later (1 Dec 2026 + 12 months → 30 Nov 2027). */
    public static function computeEndDate($start, int $months): Carbon
    {
        return Carbon::parse($start)->addMonthsNoOverflow($months)->subDay();
    }

    public function isEditable(): bool
    {
        return ! $this->status->isLocked();
    }

    // ---- money helpers (all amounts per billing period) ----
    public function salesTaxAmount(): float
    {
        return $this->apply_sales_tax ? round((float) $this->fee * (float) $this->sales_tax_rate / 100, 2) : 0.0;
    }

    public function totalAmount(): float
    {
        return round((float) $this->fee + $this->salesTaxAmount(), 2);
    }

    public function whtAmount(): float
    {
        if (! $this->apply_wht) {
            return 0.0;
        }
        $base = Settings::get('wht_base', 'fee') === 'total' ? $this->totalAmount() : (float) $this->fee;

        return round($base * (float) $this->wht_rate / 100, 2);
    }

    public function netReceivable(): float
    {
        return round($this->totalAmount() - $this->whtAmount(), 2);
    }

    public function renewalFee(int $renewals = 1): float
    {
        $fee = (float) $this->fee;
        for ($i = 0; $i < $renewals; $i++) {
            $fee = round($fee * (1 + (float) $this->renewal_increase_percent / 100), 2);
        }

        return $fee;
    }

    /** Next agreement number, e.g. CSZ-WMA-2026-0001. */
    public static function nextAgreementNumber(Template $template, ?Carbon $date = null): string
    {
        $year = ($date ?? now())->format('Y');
        $prefix = Settings::get('agreement_prefix', 'CSZ').'-'.$template->code.'-'.$year.'-';
        $last = static::withTrashed()->where('agreement_no', 'like', $prefix.'%')->orderByDesc('agreement_no')->value('agreement_no');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
