<?php

namespace App\Filament\Resources\Templates;

use App\Enums\BillingCycle;
use App\Enums\FieldType;
use App\Models\Template;
use App\Services\ContractRenderer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Details')->icon(Heroicon::OutlinedInformationCircle)->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')->required()->maxLength(255)->columnSpan(2),
                        TextInput::make('code')->required()->maxLength(10)->alphaDash()->unique(ignoreRecord: true)
                            ->helperText('Used in agreement numbers, e.g. WMA → CSZ-WMA-2026-0001')
                            ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state)),
                        TextInput::make('category'),
                        Toggle::make('is_active')->label('Available for new contracts')->default(true)->inline(false),
                        Textarea::make('description')->columnSpanFull()->rows(2),
                    ]),
                    Section::make('Defaults for new contracts')->columns(3)->schema([
                        Select::make('default_billing_cycle')->label('Billing cycle')->options(BillingCycle::class)->required()->default('monthly'),
                        TextInput::make('default_fee')->label('Fee')->numeric()->prefix('PKR'),
                        TextInput::make('default_term_months')->label('Term (months)')->numeric()->required()->default(12)->minValue(1),
                        TextInput::make('min_fee')->label('Lowest fee staff can set')->numeric()->prefix('PKR')
                            ->helperText('Managers can go outside these limits.'),
                        TextInput::make('max_fee')->label('Highest fee staff can set')->numeric()->prefix('PKR'),
                        TextInput::make('default_renewal_increase')->label('Increase at renewal')->numeric()->suffix('%')->default(10),
                        TextInput::make('default_late_fee')->label('Late fee')->numeric()->suffix('%')->default(5),
                    ]),
                ]),
                Tab::make('Fields')->icon(Heroicon::OutlinedPencilSquare)->schema([
                    Html::make('<p class="text-sm text-gray-500">Fields are the blanks staff fill in for each contract. Put <code>{{key}}</code> in the contract text where the value should appear.</p>'),
                    Repeater::make('fields')->hiddenLabel()->addActionLabel('Add field')->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => ($state['label'] ?? 'New field').(! empty($state['key']) ? '  ·  {{'.$state['key'].'}}' : ''))
                        ->columns(4)->schema([
                            TextInput::make('key')->required()->regex('/^[a-z][a-z0-9_]*$/')->distinct()
                                ->helperText('lowercase_with_underscores')
                                ->notIn(array_keys(ContractRenderer::systemVariables()))
                                ->validationMessages(['not_in' => 'This name is a built-in value; pick another.']),
                            TextInput::make('label')->required(),
                            Select::make('type')->options(FieldType::class)->required()->default('text')->live(),
                            Toggle::make('required')->inline(false)->default(true),
                            TextInput::make('default')->label('Default value')->columnSpan(2),
                            TextInput::make('help')->label('Help text for staff')->columnSpan(2),
                            TagsInput::make('options')->columnSpanFull()
                                ->visible(fn (Get $get) => $get('type') === 'select' || $get('type') === FieldType::Select),
                        ]),
                ]),
                Tab::make('Optional clauses')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->schema([
                    Html::make('<p class="text-sm text-gray-500">An optional clause is text that staff can switch on or off per contract. In the contract text, wrap it like this:<br><code>[[if clause_key]]</code> … text … <code>[[endif]]</code> &nbsp; or with an alternative: <code>[[if clause_key]]</code> … <code>[[else]]</code> … <code>[[endif]]</code></p>'),
                    Repeater::make('clauses')->hiddenLabel()->addActionLabel('Add optional clause')->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => ($state['label'] ?? 'New clause').(! empty($state['key']) ? '  ·  [[if '.$state['key'].']]' : ''))
                        ->columns(3)->schema([
                            TextInput::make('key')->required()->regex('/^[a-z][a-z0-9_]*$/')->distinct(),
                            TextInput::make('label')->required(),
                            Toggle::make('default_on')->label('On by default')->inline(false)->default(true),
                            TextInput::make('description')->label('Explanation for staff')->columnSpanFull(),
                        ]),
                ]),
                Tab::make('Contract text')->icon(Heroicon::OutlinedDocumentText)->schema([
                    Grid::make(['default' => 1, 'xl' => 4])->schema([
                        RichEditor::make('body')->hiddenLabel()->required()->columnSpan(['xl' => 3])
                            ->extraInputAttributes(['style' => 'min-height: 60vh'])
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'link'],
                                ['h2', 'h3'],
                                ['bulletList', 'orderedList', 'table'],
                                ['mergeTags'],
                                ['undo', 'redo'],
                            ])
                            ->mergeTags(fn (Get $get) => static::mergeTagList($get('fields') ?? [])),
                        Section::make('Values you can use')->columnSpan(['xl' => 1])->compact()->schema([
                            Html::make(fn (Get $get) => static::placeholderHelp($get('fields') ?? [])),
                        ]),
                    ]),
                    TextInput::make('change_note')->label('What changed (saved with the new version)')
                        ->dehydrated(false)->placeholder('e.g. Added backup retention clause'),
                ]),
            ]),
        ]);
    }

    public static function mergeTagList(array $fields): array
    {
        $tags = [];
        foreach ($fields as $f) {
            if (! empty($f['key'])) {
                $tags[$f['key']] = $f['label'] ?? $f['key'];
            }
        }
        foreach (ContractRenderer::systemVariables() as $k => $desc) {
            $tags[$k] = $desc;
        }

        return $tags;
    }

    public static function placeholderHelp(array $fields): string
    {
        $row = fn ($k, $d) => '<li class="py-0.5"><code class="text-primary-600 select-all">{{'.e($k).'}}</code><br><span class="text-xs text-gray-500">'.e($d).'</span></li>';
        $html = '<div class="text-sm max-h-[60vh] overflow-y-auto"><p class="font-semibold">This template\'s fields</p><ul>';
        $any = false;
        foreach ($fields as $f) {
            if (! empty($f['key'])) {
                $html .= $row($f['key'], $f['label'] ?? '');
                $any = true;
            }
        }
        if (! $any) {
            $html .= '<li class="text-xs text-gray-500">None yet: add them on the Fields tab.</li>';
        }
        $html .= '</ul><p class="font-semibold mt-3">Built-in values</p><ul>';
        foreach (ContractRenderer::systemVariables() as $k => $d) {
            $html .= $row($k, $d);
        }

        return $html.'</ul></div>';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (Template $t) => $t->description ? str($t->description)->limit(80) : null),
                TextColumn::make('code')->badge()->color('gray'),
                TextColumn::make('default_billing_cycle')->label('Billing'),
                TextColumn::make('currentVersion.version')->label('Version')->prefix('v'),
                TextColumn::make('contracts_count')->counts('contracts')->label('Contracts'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->since()->label('Updated'),
            ])
            ->recordActions([EditAction::make()->label(fn () => auth()->user()?->isAdmin() ? 'Edit' : 'View')]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTemplates::route('/'),
            'create' => Pages\CreateTemplate::route('/create'),
            'edit' => Pages\EditTemplate::route('/{record}/edit'),
        ];
    }
}
