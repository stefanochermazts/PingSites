<?php

namespace App\Filament\Resources\MaintenanceWindows;

use App\Filament\Resources\MaintenanceWindows\Pages\CreateMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\Pages\EditMaintenanceWindow;
use App\Filament\Resources\MaintenanceWindows\Pages\ListMaintenanceWindows;
use App\Filament\Resources\MaintenanceWindows\Schemas\MaintenanceWindowForm;
use App\Filament\Resources\MaintenanceWindows\Tables\MaintenanceWindowsTable;
use App\Models\MaintenanceWindow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaintenanceWindowResource extends Resource
{
    protected static ?string $model = MaintenanceWindow::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Manutenzioni';

    protected static ?string $modelLabel = 'Manutenzione';

    protected static ?string $pluralModelLabel = 'Manutenzioni';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoraggio';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceWindowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceWindowsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceWindows::route('/'),
            'create' => CreateMaintenanceWindow::route('/create'),
            'edit' => EditMaintenanceWindow::route('/{record}/edit'),
        ];
    }
}
