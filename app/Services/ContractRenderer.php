<?php

namespace App\Services;

use App\Enums\FieldType;
use App\Models\Contract;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use NumberFormatter;

/**
 * Turns a template body into the contract text.
 *
 * Template syntax (works inside the rich-text editor):
 *   {{field_key}}                     a template field or a system value (see systemVariables())
 *   [[if clause_key]] ... [[endif]]   optional clause, shown when the clause is switched on
 *   [[if clause_key]] ... [[else]] ... [[endif]]
 *   [[if not clause_key]] ... [[endif]]
 */
class ContractRenderer
{
    /** @var array<string,string> label of each value that was empty while rendering */
    public array $missing = [];

    /** Descriptions of the values every template can use. Shown in the template editor. */
    public static function systemVariables(): array
    {
        return [
            'agreement_no' => 'Agreement number, e.g. CSZ-WMA-2026-0001',
            'agreement_date' => 'Date the agreement is made (blank line if not set)',
            'start_date' => 'Start date, e.g. 1 December 2026',
            'end_date' => 'End date, e.g. 30 November 2027',
            'term_text' => 'Length of the term in words, e.g. one year (12 months)',
            'fee' => 'Fee per billing period, e.g. PKR 7,500',
            'fee_words' => 'Fee in words, e.g. Pakistani Rupees Seven Thousand Five Hundred only',
            'fee_per' => 'Billing period wording, e.g. per month',
            'billing_cycle' => 'Billing cycle, e.g. Monthly',
            'invoice_day' => 'Invoice day of the month, e.g. 1st',
            'due_day' => 'Payment due day of the month, e.g. 5th',
            'late_fee_percent' => 'Late fee, e.g. 5%',
            'renewal_increase_percent' => 'Increase at each renewal, e.g. 10%',
            'renewal_fee' => 'Fee after the first renewal, e.g. PKR 8,250',
            'renewal_fee_2' => 'Fee after the second renewal, e.g. PKR 9,075',
            'sales_tax_name' => 'Name of the sales tax, e.g. SRB Sales Tax',
            'sales_tax_rate' => 'Sales tax rate, e.g. 15%',
            'sales_tax_amount' => 'Sales tax per period',
            'total_amount' => 'Fee plus sales tax per period',
            'wht_rate' => 'Withholding tax rate, e.g. 4%',
            'wht_amount' => 'Withholding tax the client may deduct per period',
            'net_amount' => 'Amount CSZ receives after withholding tax',
            'fee_table' => 'Table of fee, sales tax, total, withholding tax and net amount',
            'client_name' => 'Client company name',
            'client_contact' => 'Client contact person',
            'client_designation' => 'Client contact person\'s designation',
            'client_address' => 'Client full address',
            'client_email' => 'Client email',
            'client_phone' => 'Client phone',
            'client_ntn' => 'Client NTN',
            'client_website' => 'Client website',
            'company_name' => 'Creative Solution Zone',
            'company_address' => 'CSZ office address',
            'company_phone' => 'CSZ phone',
            'company_email' => 'CSZ email',
            'support_email' => 'CSZ support email',
            'company_website' => 'CSZ website',
            'company_ntn' => 'CSZ NTN',
            'signatory_name' => 'CSZ authorised signatory',
            'jurisdiction_city' => 'City whose courts handle disputes',
            'business_hours' => 'CSZ business hours',
        ];
    }

    /** Render the body of a contract (uses the frozen copy once approved). */
    public function render(Contract $contract, bool $useFrozen = true): string
    {
        if ($useFrozen && $contract->frozen_html) {
            return $contract->frozen_html;
        }

        $version = $contract->templateVersion;

        return $this->renderBody(
            (string) $version?->body,
            $this->variables($contract),
            array_merge($this->systemFlags($contract), $contract->clause_flags ?? []),
        );
    }

    /** Switches every template can test with [[if …]], worked out from the contract. */
    public static function systemFlags(Contract $contract): array
    {
        return [
            'recurring_billing' => (bool) $contract->billing_cycle?->isRecurring(),
            'withholding_tax' => (bool) $contract->apply_wht,
            'sales_tax' => (bool) $contract->apply_sales_tax,
            'late_fee' => (float) $contract->late_fee_percent > 0,
            'renewal_increase' => (float) $contract->renewal_increase_percent > 0,
        ];
    }

    /** @param array<string,string> $vars already-escaped HTML values */
    public function renderBody(string $body, array $vars, array $flags): string
    {
        $this->missing = [];

        // Merge tags inserted with the editor's "Merge tags" button.
        $body = preg_replace('#<span[^>]*data-type="mergeTag"[^>]*data-id="([\w.-]+)"[^>]*>.*?</span>#is', '{{$1}}', $body);
        $body = preg_replace('#<span[^>]*data-id="([\w.-]+)"[^>]*data-type="mergeTag"[^>]*>.*?</span>#is', '{{$1}}', $body);

        // Markers typed on their own line in the editor arrive wrapped in a paragraph: unwrap them.
        $body = preg_replace('#<p[^>]*>\s*(\[\[(?:if\s+(?:not\s+)?[\w.-]+|else|endif)\]\])\s*</p>#i', '$1', $body);

        // Optional clauses, innermost first.
        $pattern = '#\[\[if\s+(not\s+)?([\w.-]+)\]\]((?:(?!\[\[if\s).)*?)\[\[endif\]\]#is';
        $guard = 0;
        while (preg_match($pattern, $body) && $guard++ < 200) {
            $body = preg_replace_callback($pattern, function ($m) use ($flags) {
                $on = ! empty($flags[$m[2]]);
                if ($m[1] !== '') {
                    $on = ! $on;
                }
                $parts = preg_split('#\[\[else\]\]#i', $m[3], 2);

                return $on ? $parts[0] : ($parts[1] ?? '');
            }, $body);
        }

        // A placeholder alone in its paragraph may hold a block (table or list): mark it.
        $body = preg_replace('#<p[^>]*>\s*\{\{\s*([\w.-]+)\s*\}\}\s*</p>#', '{{!$1}}', $body);

        // Fields.
        $body = preg_replace_callback('#\{\{(!?)\s*([\w.-]+)\s*\}\}#', function ($m) use ($vars) {
            $key = $m[2];
            $block = $m[1] === '!';
            if (array_key_exists($key, $vars) && $vars[$key] !== '' && $vars[$key] !== null) {
                $v = (string) $vars[$key];
                $isBlock = (bool) preg_match('#^\s*<(table|ul|ol|div|h\d)#i', $v);

                return ($block && ! $isBlock) ? '<p>'.$v.'</p>' : $v;
            }
            $this->missing[$key] = $key;
            $span = '<span class="missing">['.e($key).']</span>';

            return $block ? '<p>'.$span.'</p>' : $span;
        }, $body);

        // Remove paragraphs and list items left empty by switched-off clauses.
        $body = preg_replace('#<(p|li)[^>]*>(\s|&nbsp;|<br\s*/?>|<p>\s*</p>)*</\1>#i', '', $body);

        return preg_replace('#<(ul|ol)[^>]*>\s*</\1>#i', '', $body);
    }

    /** All values for a contract, as escaped HTML strings. */
    public function variables(Contract $contract): array
    {
        $s = Settings::all();
        $c = $contract->client;
        $money = fn ($v) => self::money($v, $contract->currency);

        $vars = [
            'agreement_no' => e($contract->agreement_no),
            'agreement_date' => $contract->agreement_date ? self::date($contract->agreement_date) : '____________________',
            'start_date' => self::date($contract->start_date),
            'end_date' => self::date($contract->end_date),
            'term_text' => e(self::termText((int) $contract->term_months)),
            'fee' => $money($contract->fee),
            'fee_words' => e(self::moneyWords((float) $contract->fee)),
            'fee_per' => e($contract->billing_cycle?->per() ?? ''),
            'billing_cycle' => e($contract->billing_cycle?->getLabel() ?? ''),
            'invoice_day' => $contract->invoice_day ? self::ordinal($contract->invoice_day) : '',
            'due_day' => $contract->due_day ? self::ordinal($contract->due_day) : '',
            'late_fee_percent' => self::percent($contract->late_fee_percent),
            'renewal_increase_percent' => self::percent($contract->renewal_increase_percent),
            'renewal_fee' => $money($contract->renewalFee(1)),
            'renewal_fee_2' => $money($contract->renewalFee(2)),
            'sales_tax_name' => e($s['sales_tax_name']),
            'sales_tax_rate' => self::percent($contract->sales_tax_rate),
            'sales_tax_amount' => $money($contract->salesTaxAmount()),
            'total_amount' => $money($contract->totalAmount()),
            'wht_rate' => self::percent($contract->wht_rate),
            'wht_amount' => $money($contract->whtAmount()),
            'net_amount' => $money($contract->netReceivable()),
            'fee_table' => $this->feeTable($contract),
            'client_name' => e($c?->company_name),
            'client_contact' => e($c?->contact_name),
            'client_designation' => e($c?->contact_designation),
            'client_address' => e($c?->fullAddress()),
            'client_email' => e($c?->email),
            'client_phone' => e($c?->phone),
            'client_ntn' => e($c?->ntn),
            'client_website' => e($c?->website),
        ];
        foreach (['company_name', 'company_address', 'company_phone', 'company_email', 'support_email', 'company_website', 'company_ntn', 'signatory_name', 'jurisdiction_city', 'business_hours'] as $k) {
            $vars[$k] = e($s[$k] ?? '');
        }

        // Template fields.
        $values = $contract->field_values ?? [];
        foreach (($contract->templateVersion?->fields ?? []) as $field) {
            $key = $field['key'] ?? null;
            if (! $key) {
                continue;
            }
            $vars[$key] = self::formatField($field, $values[$key] ?? ($field['default'] ?? null), $contract->currency);
        }

        return $vars;
    }

    public function feeTable(Contract $contract): string
    {
        $m = fn ($v) => self::money($v, $contract->currency);
        $per = $contract->billing_cycle?->per();
        $rows = [['Fee'.($per ? ' ('.$per.')' : ''), $m($contract->fee)]];
        if ($contract->apply_sales_tax) {
            $rows[] = [e(Settings::get('sales_tax_name')).' @ '.self::percent($contract->sales_tax_rate), $m($contract->salesTaxAmount())];
            $rows[] = ['<b>Total invoice amount</b>', '<b>'.$m($contract->totalAmount()).'</b>'];
        }
        if ($contract->apply_wht) {
            $base = Settings::get('wht_base', 'fee') === 'total' ? 'total' : 'fee';
            $rows[] = ['Less: income tax withheld by the Client @ '.self::percent($contract->wht_rate).' of the '.$base, '('.$m($contract->whtAmount()).')'];
            $rows[] = ['<b>Net amount payable to CSZ</b>', '<b>'.$m($contract->netReceivable()).'</b>'];
        }
        $html = '<table class="fee-table">';
        foreach ($rows as [$a, $b]) {
            $html .= '<tr><td>'.$a.'</td><td class="num">'.$b.'</td></tr>';
        }

        return $html.'</table>';
    }

    // ---------- formatting helpers ----------

    public static function formatField(array $field, mixed $value, string $currency = 'PKR'): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        $type = FieldType::tryFrom($field['type'] ?? 'text') ?? FieldType::Text;

        return match ($type) {
            FieldType::Money => self::money($value, $currency),
            FieldType::Percent => self::percent($value),
            FieldType::Number => e(rtrim(rtrim(number_format((float) $value, 2), '0'), '.')),
            FieldType::Date => self::date($value),
            FieldType::Textarea => nl2br(e((string) $value)),
            FieldType::List => '<ul>'.collect(preg_split('/\r?\n/', (string) $value))->map(fn ($l) => trim(ltrim(trim($l), '-•*')))->filter()->map(fn ($l) => '<li>'.e($l).'</li>')->implode('').'</ul>',
            default => e((string) $value),
        };
    }

    public static function money(mixed $value, string $currency = 'PKR'): string
    {
        $v = (float) $value;
        $decimals = fmod($v, 1.0) == 0.0 ? 0 : 2;

        return e($currency.' '.number_format($v, $decimals));
    }

    public static function percent(mixed $value): string
    {
        return e(rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%');
    }

    public static function date(mixed $value): string
    {
        return $value ? e(Carbon::parse($value)->format('j F Y')) : '';
    }

    public static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }

    public static function termText(int $months): string
    {
        if ($months > 0 && $months % 12 === 0) {
            $years = $months / 12;
            $w = $years === 1 ? 'one year' : self::words($years).' years';

            return "$w ($months months)";
        }

        return $months === 1 ? 'one month' : self::words($months)." months";
    }

    public static function words(int|float $n): string
    {
        if (class_exists(NumberFormatter::class)) {
            $f = new NumberFormatter('en', NumberFormatter::SPELLOUT);

            return str_replace('-', ' ', (string) $f->format($n));
        }

        return (string) $n;
    }

    public static function moneyWords(float $amount): string
    {
        $rupees = (int) floor($amount);
        $paisa = (int) round(($amount - $rupees) * 100);
        $text = 'Pakistani Rupees '.ucwords(self::words($rupees));
        if ($paisa > 0) {
            $text .= ' and '.ucwords(self::words($paisa)).' Paisa';
        }

        return $text.' only';
    }
}
