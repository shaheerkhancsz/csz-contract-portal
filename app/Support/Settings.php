<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide settings editable by admins (Settings page). Values are cached.
 */
class Settings
{
    public const DEFAULTS = [
        'company_name' => 'Creative Solution Zone',
        'company_tagline' => 'Advancement Through Technology',
        'company_address' => '1st Floor Mansoor Tower, G5/5 Main Clifton, Karachi, Pakistan',
        'company_phone' => '+92 307 2228391',
        'company_email' => 'info@creativesolutionzone.com',
        'support_email' => 'support@creativesolutionzone.com',
        'company_website' => 'www.creativesolutionzone.com',
        'company_ntn' => '',
        'company_strn' => '',
        'signatory_name' => '',
        'signatory_designation' => 'Chief Executive',
        'jurisdiction_city' => 'Karachi',
        'agreement_prefix' => 'CSZ',
        'logo_path' => '',
        'sales_tax_name' => 'SRB Sales Tax',
        'sales_tax_rate' => '15',
        'wht_rate' => '4',
        'wht_base' => 'fee',            // fee | total  (confirm with tax adviser)
        'default_invoice_day' => '1',
        'default_due_day' => '5',
        'brand_primary' => '#64BCE9',
        'brand_dark' => '#1A1E22',
        'brand_accent' => '#F2A65A',
        'brand_grey' => '#7C8286',
        'business_hours' => 'Monday to Saturday, 10:00 am to 6:00 pm (Pakistan Standard Time), excluding public holidays',
    ];

    public static function all(): array
    {
        return Cache::rememberForever('csz.settings', function () {
            $stored = Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : [];

            return array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null));
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }
        Cache::forget('csz.settings');
    }
}
