<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * The first three templates. Safe to run again: existing templates (matched by code) are left alone.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $t) {
            if (Template::where('code', $t['code'])->exists()) {
                continue;
            }
            $template = Template::create($t);
            $template->publishVersionIfChanged('Starter template');
        }
    }

    private function body(string $file): string
    {
        $dir = __DIR__.'/templates/';
        $html = file_get_contents($dir.$file);
        foreach (['parties', 'term', 'fees', 'end'] as $part) {
            $html = str_replace('@'.$part, trim(file_get_contents($dir.'_common_'.$part.'.html')), $html);
        }

        // Markers and block values on their own line become their own paragraph, so the rich-text editor keeps them intact.
        $html = preg_replace('#^\s*((?:\[\[[^\]]+\]\])|(?:\{\{\s*[\w.-]+\s*\}\}))\s*$#m', '<p>$1</p>', $html);

        return $this->numberHeadings(trim($html));
    }

    /** "<h2>Fees</h2>" → "<h2>4. Fees</h2>" */
    private function numberHeadings(string $html): string
    {
        $n = 0;

        return preg_replace_callback('#<h2>(.*?)</h2>#', function ($m) use (&$n) {
            return '<h2>'.(++$n).'. '.$m[1].'</h2>';
        }, $html);
    }

    private function f(string $key, string $label, string $type = 'text', $default = null, bool $required = true, ?string $help = null, array $options = []): array
    {
        return compact('key', 'label', 'type', 'default', 'required', 'help', 'options');
    }

    private function c(string $key, string $label, bool $on = true, ?string $description = null): array
    {
        return ['key' => $key, 'label' => $label, 'default_on' => $on, 'description' => $description];
    }

    private function templates(): array
    {
        $renewal = $this->c('auto_renewal', 'Renews when the renewal invoice is paid', true, 'Off: the agreement simply ends on the end date.');

        return [
            [
                'name' => 'WordPress Website Maintenance Agreement',
                'code' => 'WMA',
                'category' => 'Maintenance',
                'description' => 'Monthly care plan for a client\'s WordPress website: updates, backups, security, monitoring and small content changes.',
                'default_billing_cycle' => 'monthly',
                'default_fee' => 7500,
                'default_term_months' => 12,
                'default_renewal_increase' => 10,
                'default_late_fee' => 5,
                'body' => $this->body('wordpress.html'),
                'fields' => [
                    $this->f('website_url', 'Website address', 'url', null, true, 'e.g. https://z-houses.com/'),
                    $this->f('platform', 'Platform', 'text', 'WordPress (self-hosted)'),
                    $this->f('backup_frequency', 'Backup frequency', 'select', 'Weekly', true, null, ['Daily', 'Weekly', 'Monthly']),
                    $this->f('backup_retention_days', 'Backups kept for (days)', 'number', '30'),
                    $this->f('content_hours', 'Content-change hours per month', 'number', '2'),
                    $this->f('report_frequency', 'Maintenance report every', 'select', 'three months', true, null, ['month', 'three months', 'six months']),
                    $this->f('critical_response', 'First response: critical issues', 'text', '4 business hours'),
                    $this->f('normal_response', 'First response: normal requests', 'text', '2 business days'),
                    $this->f('notice_days', 'Notice to end the agreement (days)', 'number', '30'),
                ],
                'clauses' => [
                    $this->c('security', 'Security monitoring'),
                    $this->c('malware_removal', 'Malware removal included', true, 'Off: scans only; clean-up is quoted separately.'),
                    $this->c('uptime_monitoring', 'Uptime monitoring and monthly health check'),
                    $this->c('client_licences', 'Client holds licences for paid plugins/themes'),
                    $renewal,
                ],
            ],
            [
                'name' => 'Hosting Services Agreement',
                'code' => 'HOST',
                'category' => 'Hosting',
                'description' => 'Shared hosting, VPS or dedicated server: specifications, availability, backups, acceptable use.',
                'default_billing_cycle' => 'monthly',
                'default_fee' => null,
                'default_term_months' => 12,
                'default_renewal_increase' => 10,
                'default_late_fee' => 5,
                'body' => $this->body('hosting.html'),
                'fields' => [
                    $this->f('service_type', 'Type of service', 'select', 'Virtual Private Server (VPS)', true, null, ['Shared Hosting', 'Virtual Private Server (VPS)', 'Dedicated Server']),
                    $this->f('plan_name', 'Plan name', 'text', null, true, 'e.g. VPS Business 8'),
                    $this->f('specifications', 'Specifications', 'list', "4 vCPU\n8 GB RAM\n160 GB NVMe storage\n1 dedicated IPv4 address\nUnmetered bandwidth", true, 'One item per line'),
                    $this->f('operating_system', 'Operating system / panel', 'text', 'Ubuntu 24.04 LTS'),
                    $this->f('data_centre', 'Data centre location', 'select', 'Germany', true, 'Location only. The upstream provider is not named in the agreement.', ['Canada', 'France', 'Germany', 'Finland', 'United States', 'Singapore', 'Pakistan']),
                    $this->f('uptime_percent', 'Uptime commitment', 'percent', '99.9'),
                    $this->f('backup_details', 'Backup arrangement', 'textarea', 'CSZ takes a weekly snapshot of the server and keeps the last 4 snapshots.', false),
                    $this->f('setup_fee', 'One-time set-up fee', 'money', null, false),
                    $this->f('notice_days', 'Notice to end the agreement (days)', 'number', '30'),
                ],
                'clauses' => [
                    $this->c('managed', 'Managed by CSZ', true, 'Off: unmanaged, the client looks after the software.'),
                    $this->c('backups', 'Backups included'),
                    $this->c('service_credits', 'Service credits for downtime (5/10/25%)'),
                    $this->c('setup', 'One-time set-up fee', false),
                    $renewal,
                ],
            ],
            [
                'name' => 'Custom Web Application Service Level Agreement',
                'code' => 'SLA',
                'category' => 'Support',
                'description' => 'Support, maintenance and (optionally) hosting of a custom web application, with response times by priority.',
                'default_billing_cycle' => 'monthly',
                'default_fee' => null,
                'default_term_months' => 12,
                'default_renewal_increase' => 10,
                'default_late_fee' => 5,
                'body' => $this->body('sla.html'),
                'fields' => [
                    $this->f('application_name', 'Application name'),
                    $this->f('application_description', 'What the application does', 'text', null, true, 'One short phrase, e.g. "an online booking and invoicing system for its branches"'),
                    $this->f('uptime_percent', 'Availability commitment', 'percent', '99.5'),
                    $this->f('support_hours', 'Support hours', 'text', 'Monday to Saturday, 10:00 am to 6:00 pm (Pakistan Standard Time), excluding public holidays'),
                    $this->f('p1_response', 'P1 first response', 'text', '1 hour'),
                    $this->f('p1_resolution', 'P1 target fix or workaround', 'text', '8 hours'),
                    $this->f('p2_response', 'P2 first response', 'text', '4 business hours'),
                    $this->f('p3_response', 'P3 first response', 'text', '2 business days'),
                    $this->f('change_hours', 'Included change hours per month', 'number', '4'),
                    $this->f('change_rate', 'Rate for extra work (per hour)', 'money', '5000'),
                    $this->f('notice_days', 'Notice to end the agreement (days)', 'number', '60'),
                ],
                'clauses' => [
                    $this->c('hosting', 'CSZ hosts the application'),
                    $this->c('after_hours', '24/7 handling of P1 issues'),
                    $this->c('service_credits', 'Service credits for downtime'),
                    $renewal,
                ],
            ],
        ];
    }
}
