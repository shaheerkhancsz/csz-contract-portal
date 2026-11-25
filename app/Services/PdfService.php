<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * Builds the contract PDF on the CSZ letterhead.
 */
class PdfService
{
    public function __construct(private ContractRenderer $renderer) {}

    public function contractPdf(Contract $contract, bool $useFrozen = true): string
    {
        $body = $this->renderer->render($contract, $useFrozen);

        return $this->make(
            title: $contract->title,
            subtitle: 'Agreement No. '.$contract->agreement_no,
            bodyHtml: $body.$this->signatureBlock($contract),
            footerRef: $contract->agreement_no.' — '.$contract->client?->company_name,
            draft: in_array($contract->status, [ContractStatus::Draft, ContractStatus::AwaitingApproval], true),
        );
    }

    public function make(string $title, string $subtitle, string $bodyHtml, string $footerRef, bool $draft = false): string
    {
        $s = Settings::all();
        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'default_font' => 'dejavusans',
            'default_font_size' => 9.5,
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 36,
            'margin_bottom' => 26,
            'margin_header' => 10,
            'margin_footer' => 8,
        ]);
        $mpdf->SetTitle($title.' — '.$footerRef);
        $mpdf->SetAuthor($s['company_name']);
        $mpdf->SetCreator('CSZ Contract Portal');

        if ($draft) {
            $mpdf->SetWatermarkText('DRAFT', 0.07);
            $mpdf->showWatermarkText = true;
        }

        $mpdf->SetHTMLHeader($this->header($s));
        $mpdf->SetHTMLFooter($this->footer($s, $footerRef));
        $mpdf->WriteHTML($this->css($s), \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML(
            '<div class="doc-title">'.e(mb_strtoupper($title)).'</div>'.
            '<div class="doc-sub">'.e($subtitle).'</div>'.
            '<div class="body">'.$bodyHtml.'</div>',
            \Mpdf\HTMLParserMode::HTML_BODY,
        );

        return $mpdf->Output('', 'S');
    }

    private function header(array $s): string
    {
        $logo = $s['logo_path'] ? Storage::disk('local')->path($s['logo_path']) : null;
        if ($logo && is_file($logo)) {
            $brand = '<img src="'.e($logo).'" style="height:15mm;" />';
        } else {
            // Text wordmark until the official logo is uploaded under Settings.
            $brand = '<span style="font-size:17pt;font-weight:bold;color:'.$s['brand_primary'].';">CREATIVE </span>'.
                     '<span style="font-size:17pt;font-weight:bold;color:'.$s['brand_grey'].';">SOLUTION ZONE</span>';
        }

        return '<div style="border-bottom:1.2mm solid '.$s['brand_primary'].';padding-bottom:1mm;"><table width="100%"><tr>'.
            '<td style="vertical-align:bottom;">'.$brand.'</td>'.
            '<td style="text-align:right;vertical-align:bottom;font-size:8.5pt;color:'.$s['brand_grey'].';">'.
            '<i>'.e($s['company_tagline']).'</i><br/>'.e($s['company_website']).'</td>'.
            '</tr></table></div>';
    }

    private function footer(array $s, string $ref): string
    {
        $line = collect([$s['company_address'], $s['company_phone'], $s['company_email'], $s['company_website']])->filter()->map(fn ($v) => e($v))->implode('  |  ');

        return '<div style="border-top:0.3mm solid '.$s['brand_grey'].';padding-top:1.5mm;font-size:7.5pt;color:'.$s['brand_grey'].';text-align:center;">'.$line.'</div>'.
            '<table width="100%" style="font-size:7.5pt;color:'.$s['brand_grey'].';"><tr>'.
            '<td>'.e($ref).'</td><td style="text-align:right;">Page {PAGENO} of {nbpg}</td></tr></table>';
    }

    private function css(array $s): string
    {
        $p = $s['brand_primary'];
        $d = $s['brand_dark'];
        $g = $s['brand_grey'];

        return <<<CSS
body { color: {$d}; line-height: 1.45; }
.doc-title { font-size: 15pt; font-weight: bold; text-align: center; color: {$d}; margin-top: 2mm; }
.doc-sub { font-size: 8.5pt; text-align: center; color: {$g}; margin-bottom: 6mm; }
.body p { margin: 0 0 2.2mm 0; text-align: justify; }
.body h1, .body h2 { font-size: 11pt; color: {$d}; border-left: 1.4mm solid {$p}; padding-left: 2mm; margin: 5mm 0 2mm 0; }
.body h3 { font-size: 9.5pt; color: {$g}; margin: 3mm 0 1.5mm 0; }
.body ul, .body ol { margin: 0 0 2.5mm 0; padding-left: 6mm; }
.body li { margin-bottom: 1mm; }
.body li p { margin: 0; }
.body table { border-collapse: collapse; width: 100%; margin: 2mm 0 4mm 0; }
.body td, .body th { border: 0.2mm solid #C9CED2; padding: 1.6mm 2.4mm; vertical-align: top; font-size: 9pt; }
.body th { background-color: {$p}; color: #ffffff; text-align: left; }
.body td:first-child { background-color: #EEF7FC; font-weight: bold; }
.fee-table td.num { text-align: right; width: 35%; }
.missing { background-color: #FDE2E2; color: #B42318; }
.sig td { border: none; background: none; font-weight: normal; vertical-align: top; padding: 0 3mm 0 0; }
.sig .who { color: {$p}; font-weight: bold; font-size: 10pt; }
.sig .line { border-bottom: 0.2mm solid {$g}; height: 8mm; vertical-align: bottom; padding-bottom: 0.8mm; }
.sig .lbl { font-size: 8pt; color: {$g}; padding-bottom: 1.5mm; }
CSS;
    }

    private function signatureBlock(Contract $contract): string
    {
        $s = Settings::all();
        $c = $contract->client;
        $col = function (string $who, string $party, ?string $name, ?string $designation) {
            $row = fn ($label, $value = '') => '<tr><td class="line">'.e($value).'</td></tr><tr><td class="lbl">'.$label.'</td></tr>';

            return '<td width="50%"><table width="100%">'.
                '<tr><td class="who">'.e($who).'</td></tr><tr><td><b>'.e($party).'</b></td></tr>'.
                $row('Signature').$row('Name', $name ?? '').$row('Designation', $designation ?? '').$row('Date').
                '<tr><td style="height:4mm;"></td></tr><tr><td style="height:22mm;border:0.2mm dashed #C9CED2;text-align:center;vertical-align:middle;color:#9AA0A6;font-size:8pt;">Company stamp</td></tr>'.
                '</table></td>';
        };

        return '<div style="page-break-inside:avoid;margin-top:6mm;">'.
            '<p>The Parties sign this Agreement to show they accept its terms.</p>'.
            '<table class="sig" width="100%"><tr>'.
            $col('For the Service Provider', $s['company_name'], $s['signatory_name'] ?: null, $s['signatory_designation'] ?: null).
            $col('For the Client', (string) $c?->company_name, $c?->contact_name, $c?->contact_designation).
            '</tr></table></div>';
    }
}
