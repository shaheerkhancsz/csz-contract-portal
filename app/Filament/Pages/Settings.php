<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Support\Settings as Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 20;
    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(Store::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Company details on the letterhead')->columns(2)->schema([
                TextInput::make('company_name')->required(),
                TextInput::make('company_tagline'),
                TextInput::make('company_address')->columnSpanFull(),
                TextInput::make('company_phone'),
                TextInput::make('company_website'),
                TextInput::make('company_email')->email(),
                TextInput::make('support_email')->email(),
                TextInput::make('company_ntn')->label('CSZ NTN'),
                TextInput::make('company_strn')->label('CSZ STRN'),
                FileUpload::make('logo_path')->label('Logo for the letterhead (PNG or JPG)')->image()
                    ->disk('local')->directory('branding')->visibility('private')->maxSize(4096)->columnSpanFull()
                    ->helperText('Upload the official CSZ logo. Until then the letterhead shows the company name in brand colours.'),
            ]),
            Section::make('Signing and legal')->columns(2)->schema([
                TextInput::make('signatory_name')->label('Authorised signatory for CSZ'),
                TextInput::make('signatory_designation'),
                TextInput::make('jurisdiction_city')->label('Courts of (city)'),
                TextInput::make('business_hours')->columnSpanFull(),
                TextInput::make('agreement_prefix')->label('Agreement number prefix')->required()->maxLength(10)
                    ->helperText('e.g. CSZ → CSZ-WMA-2026-0001'),
            ]),
            Section::make('Taxes')->columns(3)
                ->description('Rates change by law: update them here and every new contract uses the new rate. Approved contracts keep the rate they were approved with.')
                ->schema([
                    TextInput::make('sales_tax_name')->required(),
                    TextInput::make('sales_tax_rate')->numeric()->suffix('%')->required(),
                    TextInput::make('wht_rate')->label('Withholding tax rate')->numeric()->suffix('%')->required(),
                    Radio::make('wht_base')->label('Withholding tax is worked out on')->columnSpanFull()
                        ->options(['fee' => 'The fee only (before sales tax)', 'total' => 'The total including sales tax'])
                        ->helperText('Confirm this with CSZ\'s tax adviser.'),
                ]),
            Section::make('Billing defaults')->columns(2)->schema([
                TextInput::make('default_invoice_day')->numeric()->minValue(1)->maxValue(28),
                TextInput::make('default_due_day')->label('Default payment due day')->numeric()->minValue(1)->maxValue(28),
            ]),
            Section::make('Brand colours')->columns(4)->collapsed()->schema([
                ColorPicker::make('brand_primary')->label('Sky blue'),
                ColorPicker::make('brand_grey')->label('Grey'),
                ColorPicker::make('brand_dark')->label('Charcoal'),
                ColorPicker::make('brand_accent')->label('Accent'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Store::setMany(collect($data)->map(fn ($v) => $v ?? '')->all());
        ActivityLog::record('settings', null, 'Settings updated');
        Notification::make()->success()->title('Settings saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('sample')->label('Preview letterhead')->color('gray')->icon('heroicon-o-eye')
            ->url(route('files.letterhead'))->openUrlInNewTab()];
    }
}
