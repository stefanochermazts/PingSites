<?php

namespace App\Filament\Resources\StatusPages;

use App\Filament\Resources\StatusPages\Pages\CreateStatusPage;
use App\Filament\Resources\StatusPages\Pages\EditStatusPage;
use App\Filament\Resources\StatusPages\Pages\ListStatusPages;
use App\Filament\Resources\StatusPages\Schemas\StatusPageForm;
use App\Filament\Resources\StatusPages\Tables\StatusPagesTable;
use App\Models\StatusPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StatusPageResource extends Resource
{
    protected static ?string $model = StatusPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Status page';

    protected static ?string $modelLabel = 'Status page';

    protected static ?string $pluralModelLabel = 'Status page';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoraggio';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return StatusPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StatusPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatusPages::route('/'),
            'create' => CreateStatusPage::route('/create'),
            'edit' => EditStatusPage::route('/{record}/edit'),
        ];
    }
}
