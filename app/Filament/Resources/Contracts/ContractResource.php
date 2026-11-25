<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\BillingCycle;
use App\Enums\ContractStatus;
use App\Enums\FieldType;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\ContractRenderer;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'agreement_no';

    public static function getNavigationBadge(): ?string
    {
        $n = Contract::where('status', ContractStatus::AwaitingApproval)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Awaiting approval';
    }

    // ------------------------------------------------------------------ form

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('template_version_id'),

            Section::make('Client and template')->columnSpanFull()->columns(2)->schema([
                Select::make('client_id')->label('Client')->required()
                    ->relationship('client', 'company_name')->searchable()->preload()
                    ->default(fn () => request()->query('client'))
                    ->createOptionForm(ClientResource::formFields())
                    ->createOptionModalHeading('New client'),
                Select::make('template_id')->label('Template')->required()
                    ->options(fn () => Template::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->disabledOn('edit')->dehydrated()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => static::applyTemplateDefaults($state, $set)),
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull()
                    ->helperText('Shown as the heading of the agreement.'),
                Text::make(fn (?Contract $record) => new HtmlString(
                    $record?->review_note ? '<span class="text-danger-600 font-medium">Manager\'s note: '.e($record->review_note).'</span>' : ''
                ))->visible(fn (?Contract $record) => filled($record?->review_note))->columnSpanFull(),
            ]),

            Section::make('Term and fees')->columnSpanFull()->columns(4)
                ->visible(fn (Get $get) => filled($get('template_id')))
                ->schema([
                    DatePicker::make('start_date')->required()->native(false)->displayFormat('d M Y')->live()
                        ->default(fn () => now()->addMonthNoOverflow()->startOfMonth()),
                    TextInput::make('term_months')->label('Term (months)')->numeric()->required()->minValue(1)->maxValue(120)->live(onBlur: true),
                    Text::make(fn (Get $get) => static::endDateText($get('start_date'), $get('term_months')))->columnSpan(2),
                    DatePicker::make('agreement_date')->label('Agreement date')->native(false)->displayFormat('d M Y')
                        ->helperText('Leave empty to use the approval date.'),

                    TextInput::make('fee')->required()->numeric()->minValue(0)->prefix('PKR')->live(onBlur: true)
                        ->minValue(fn (Get $get) => auth()->user()->isManager() ? 0 : (Template::find($get('template_id'))?->min_fee ?? 0))
                        ->maxValue(fn (Get $get) => auth()->user()->isManager() ? null : Template::find($get('template_id'))?->max_fee)
                        ->helperText(fn (Get $get) => static::feeLimitText($get('template_id'))),
                    Select::make('billing_cycle')->options(BillingCycle::class)->required()->live(),
                    TextInput::make('invoice_day')->label('Invoice day')->numeric()->minValue(1)->maxValue(28)
                        ->visible(fn (Get $get) => static::isRecurring($get('billing_cycle'))),
                    TextInput::make('due_day')->label('Payment due day')->numeric()->minValue(1)->maxValue(28)
                        ->visible(fn (Get $get) => static::isRecurring($get('billing_cycle'))),
                    TextInput::make('late_fee_percent')->label('Late fee')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0),
                    TextInput::make('renewal_increase_percent')->label('Increase at renewal')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0),

                    Grid::make(4)->columnSpanFull()->schema([
                        Toggle::make('apply_sales_tax')->label(fn () => 'Add '.Settings::get('sales_tax_name'))->inline(false)->live()->default(true),
                        TextInput::make('sales_tax_rate')->label('Sales tax rate')->numeric()->suffix('%')
                            ->default(fn () => Settings::get('sales_tax_rate'))
                            ->visible(fn (Get $get) => (bool) $get('apply_sales_tax'))->disabled(fn () => ! auth()->user()->isManager())->dehydrated()->live(onBlur: true),
                        Toggle::make('apply_wht')->label('Client deducts withholding tax')->inline(false)->live()->default(true),
                        TextInput::make('wht_rate')->label('Withholding tax rate')->numeric()->suffix('%')
                            ->default(fn () => Settings::get('wht_rate'))
                            ->visible(fn (Get $get) => (bool) $get('apply_wht'))->disabled(fn () => ! auth()->user()->isManager())->dehydrated()->live(onBlur: true),
                    ]),
                    Text::make(fn (Get $get) => static::feeSummary($get))->columnSpanFull(),
                ]),

            Section::make('Contract details')->columnSpanFull()->columns(2)
                ->description('The blanks in this template.')
                ->visible(fn (Get $get) => filled($get('template_version_id')))
                ->schema(fn (Get $get) => static::dynamicFields($get('template_version_id'))),

            Section::make('Optional clauses')->columnSpanFull()->columns(2)
                ->visible(fn (Get $get) => filled(TemplateVersion::find($get('template_version_id'))?->clauses))
                ->schema(fn (Get $get) => static::clauseToggles($get('template_version_id'))),

            Section::make('Internal notes')->columnSpanFull()->collapsed()->schema([
                Textarea::make('internal_notes')->hiddenLabel()->rows(3)->helperText('Only staff see these notes. They are not printed.'),
            ]),
        ]);
    }

    public static function applyTemplateDefaults($templateId, Set $set): void
    {
        $t = Template::with('currentVersion')->find($templateId);
        if (! $t) {
            $set('template_version_id', null);

            return;
        }
        $v = $t->currentVersion;
        $set('template_version_id', $v?->id);
        $set('title', $t->name);
        $set('fee', $t->default_fee);
        $set('billing_cycle', $t->default_billing_cycle?->value);
        $set('term_months', $t->default_term_months);
        $set('late_fee_percent', $t->default_late_fee);
        $set('renewal_increase_percent', $t->default_renewal_increase);
        $set('invoice_day', Settings::get('default_invoice_day'));
        $set('due_day', Settings::get('default_due_day'));
        $values = [];
        foreach ($v?->fields ?? [] as $f) {
            $values[$f['key']] = $f['default'] ?? null;
        }
        $set('field_values', $values);
        $flags = [];
        foreach ($v?->clauses ?? [] as $c) {
            $flags[$c['key']] = (bool) ($c['default_on'] ?? false);
        }
        $set('clause_flags', $flags);
    }

    public static function dynamicFields($versionId): array
    {
        $v = $versionId ? TemplateVersion::find($versionId) : null;
        $out = [];
        foreach ($v?->fields ?? [] as $f) {
            $key = $f['key'] ?? null;
            if (! $key) {
                continue;
            }
            $path = 'field_values.'.$key;
            $type = FieldType::tryFrom($f['type'] ?? 'text') ?? FieldType::Text;
            $c = match ($type) {
                FieldType::Textarea => Textarea::make($path)->rows(3)->columnSpanFull(),
                FieldType::List => Textarea::make($path)->rows(4)->columnSpanFull(),
                FieldType::Number => TextInput::make($path)->numeric(),
                FieldType::Money => TextInput::make($path)->numeric()->prefix('PKR'),
                FieldType::Percent => TextInput::make($path)->numeric()->suffix('%'),
                FieldType::Date => DatePicker::make($path)->native(false)->displayFormat('d M Y'),
                FieldType::Url => TextInput::make($path)->url(),
                FieldType::Email => TextInput::make($path)->email(),
                FieldType::Select => Select::make($path)->options(collect($f['options'] ?? [])->mapWithKeys(fn ($o) => [$o => $o])->all()),
                default => TextInput::make($path),
            };
            $out[] = $c->label($f['label'] ?? $key)->required((bool) ($f['required'] ?? false))
                ->helperText($f['help'] ?? null)->live(onBlur: true);
        }

        return $out ?: [Text::make('This template has no fields.')];
    }

    public static function clauseToggles($versionId): array
    {
        $v = $versionId ? TemplateVersion::find($versionId) : null;

        return collect($v?->clauses ?? [])->filter(fn ($c) => ! empty($c['key']))->map(
            fn ($c) => Toggle::make('clause_flags.'.$c['key'])->label($c['label'] ?? $c['key'])->helperText($c['description'] ?? null)
        )->values()->all();
    }

    public static function isRecurring($cycle): bool
    {
        $c = $cycle instanceof BillingCycle ? $cycle : BillingCycle::tryFrom((string) $cycle);

        return (bool) $c?->isRecurring();
    }

    public static function endDateText($start, $months): HtmlString
    {
        if (! $start || ! (int) $months) {
            return new HtmlString('');
        }
        $end = Contract::computeEndDate($start, (int) $months);

        return new HtmlString('<div class="pt-7 text-sm">Ends on <b>'.$end->format('j F Y').'</b></div>');
    }

    public static function feeLimitText($templateId): ?string
    {
        $t = Template::find($templateId);
        if (! $t || ($t->min_fee === null && $t->max_fee === null)) {
            return null;
        }
        $range = collect([$t->min_fee !== null ? 'min PKR '.number_format((float) $t->min_fee) : null, $t->max_fee !== null ? 'max PKR '.number_format((float) $t->max_fee) : null])->filter()->implode(', ');

        return 'Template limit: '.$range.(auth()->user()->isManager() ? ' (you can override)' : '');
    }

    public static function feeSummary(Get $get): HtmlString
    {
        $fee = (float) $get('fee');
        if ($fee <= 0) {
            return new HtmlString('');
        }
        $c = new Contract([
            'fee' => $fee,
            'apply_sales_tax' => (bool) $get('apply_sales_tax'),
            'sales_tax_rate' => (float) ($get('sales_tax_rate') ?? Settings::get('sales_tax_rate')),
            'apply_wht' => (bool) $get('apply_wht'),
            'wht_rate' => (float) ($get('wht_rate') ?? Settings::get('wht_rate')),
            'renewal_increase_percent' => (float) $get('renewal_increase_percent'),
            'billing_cycle' => $get('billing_cycle'),
            'currency' => 'PKR',
        ]);
        $m = fn ($v) => 'PKR '.number_format($v, fmod($v, 1.0) == 0.0 ? 0 : 2);
        $parts = ['Invoice: <b>'.$m($c->totalAmount()).'</b>'.($c->apply_sales_tax ? ' (fee '.$m($fee).' + tax '.$m($c->salesTaxAmount()).')' : '')];
        if ($c->apply_wht) {
            $parts[] = 'client withholds '.$m($c->whtAmount()).', net to CSZ <b>'.$m($c->netReceivable()).'</b>';
        }
        if ((float) $get('renewal_increase_percent') > 0) {
            $parts[] = 'fee after renewal '.$m($c->renewalFee());
        }

        return new HtmlString('<div class="text-sm rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2">'.implode(' · ', $parts).'</div>');
    }

    /** Called by the create/edit pages before saving. */
    public static function prepareForSave(array $data, ?Contract $record = null): array
    {
        $data['end_date'] = Contract::computeEndDate($data['start_date'], (int) $data['term_months'])->toDateString();
        $data['currency'] ??= 'PKR';
        $cycle = BillingCycle::tryFrom((string) ($data['billing_cycle'] instanceof BillingCycle ? $data['billing_cycle']->value : $data['billing_cycle']));
        if (! $cycle?->isRecurring()) {
            $data['invoice_day'] = null;
            $data['due_day'] = null;
        }
        $data['sales_tax_rate'] ??= Settings::get('sales_tax_rate');
        $data['wht_rate'] ??= Settings::get('wht_rate');
        $data['field_values'] = array_map(fn ($v) => $v instanceof Carbon ? $v->toDateString() : $v, $data['field_values'] ?? []);
        $data['clause_flags'] = array_map(fn ($v) => (bool) $v, $data['clause_flags'] ?? []);

        return $data;
    }

    // ------------------------------------------------------------------ view

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([
                Section::make('Contract text')->columnSpan(['lg' => 2])
                    ->description(fn (Contract $r) => $r->frozen_html ? 'Approved text: frozen and no longer changes.' : 'Live preview of the draft.')
                    ->schema([
                        Html::make(fn (Contract $record) => new HtmlString(
                            '<div class="csz-contract">'.app(ContractRenderer::class)->render($record).'</div>'
                        )),
                    ]),
                Grid::make(1)->columnSpan(['lg' => 1])->schema([
                    Section::make('Summary')->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('agreement_no')->label('Agreement no.')->copyable(),
                        TextEntry::make('client.company_name')->label('Client')
                            ->url(fn (Contract $r) => ClientResource::getUrl('view', ['record' => $r->client_id])),
                        TextEntry::make('template.name')->label('Template')
                            ->suffix(fn (Contract $r) => ' · v'.$r->templateVersion?->version),
                        TextEntry::make('period')->label('Term')
                            ->state(fn (Contract $r) => $r->start_date->format('j M Y').' – '.$r->end_date->format('j M Y').' ('.$r->term_months.' months)'),
                        TextEntry::make('fee')->state(fn (Contract $r) => 'PKR '.number_format((float) $r->fee).' '.$r->billing_cycle?->per())
                            ->helperText(fn (Contract $r) => 'Invoice PKR '.number_format($r->totalAmount(), 2).' incl. tax · net after WHT PKR '.number_format($r->netReceivable(), 2)),
                        TextEntry::make('renewal')->label('At renewal')
                            ->state(fn (Contract $r) => (float) $r->renewal_increase_percent > 0
                                ? '+'.rtrim(rtrim($r->renewal_increase_percent, '0'), '.').'% → PKR '.number_format($r->renewalFee()) : 'No increase'),
                        TextEntry::make('creator.name')->label('Drafted by')->placeholder('—')
                            ->helperText(fn (Contract $r) => $r->created_at?->format('j M Y, H:i')),
                        TextEntry::make('approver.name')->label('Approved by')->placeholder('Not yet')
                            ->helperText(fn (Contract $r) => $r->approved_at?->format('j M Y, H:i')),
                        TextEntry::make('review_note')->label('Manager\'s note')->color('danger')->visible(fn (Contract $r) => filled($r->review_note)),
                        TextEntry::make('internal_notes')->placeholder('—')->visible(fn (Contract $r) => filled($r->internal_notes)),
                    ]),
                    Section::make('Documents')->compact()->schema([
                        RepeatableEntry::make('documents')->hiddenLabel()->placeholder('None yet. A PDF is saved when a manager approves.')
                            ->schema([
                                TextEntry::make('kind')->hiddenLabel()->weight('bold')
                                    ->state(fn ($record) => $record->kindLabel())
                                    ->url(fn ($record) => route('files.document', $record))->openUrlInNewTab(),
                                TextEntry::make('sha256')->hiddenLabel()->size('xs')->color('gray')
                                    ->state(fn ($record) => $record->created_at->format('j M Y, H:i').' · SHA-256 '.substr($record->sha256, 0, 16).'…')
                                    ->tooltip(fn ($record) => $record->sha256),
                            ]),
                    ]),
                    Section::make('Activity')->compact()->collapsible()->schema([
                        RepeatableEntry::make('activities')->hiddenLabel()->placeholder('No activity yet.')
                            ->schema([
                                TextEntry::make('description')->hiddenLabel()
                                    ->helperText(fn ($record) => ($record->user?->name ?? 'System').' · '.$record->created_at?->format('j M Y, H:i')),
                            ]),
                    ]),
                ]),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ table

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agreement_no')->label('Agreement no.')->searchable()->sortable()->weight('bold'),
                TextColumn::make('client.company_name')->label('Client')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(40)->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('start_date')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->date('d M Y')->sortable()
                    ->color(fn (Contract $r) => in_array($r->status, [ContractStatus::Active, ContractStatus::Expiring]) && $r->end_date->lte(now()->addDays(30)) ? 'warning' : null),
                TextColumn::make('fee')->numeric(0)->prefix('PKR ')->suffix(fn (Contract $r) => ' '.$r->billing_cycle?->per())->sortable(),
                TextColumn::make('creator.name')->label('By')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->date('d M Y')->label('Created')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ContractStatus::class)->multiple(),
                SelectFilter::make('template_id')->label('Template')->relationship('template', 'name'),
                SelectFilter::make('client_id')->label('Client')->relationship('client', 'company_name')->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Contract $r) => static::getUrl('view', ['record' => $r]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['client', 'template']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['agreement_no', 'title', 'client.company_name'];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'view' => Pages\ViewContract::route('/{record}'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
