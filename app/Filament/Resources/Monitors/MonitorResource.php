<?php

namespace App\Filament\Resources\Monitors;

use App\Filament\Resources\Monitors\Pages\CreateMonitor;
use App\Filament\Resources\Monitors\Pages\EditMonitor;
use App\Filament\Resources\Monitors\Pages\ListMonitors;
use App\Filament\Resources\Monitors\Pages\ViewMonitor;
use App\Filament\Resources\Monitors\RelationManagers\ChecksRelationManager;
use App\Filament\Resources\Monitors\RelationManagers\IncidentsRelationManager;
use App\Filament\Resources\Monitors\Schemas\MonitorForm;
use App\Filament\Resources\Monitors\Schemas\MonitorInfolist;
use App\Filament\Resources\Monitors\Tables\MonitorsTable;
use App\Models\Monitor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MonitorResource extends Resource
{
    protected static ?string $model = Monitor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Monitor';

    protected static ?string $modelLabel = 'Monitor';

    protected static ?string $pluralModelLabel = 'Monitor';

    protected static string|UnitEnum|null $navigationGroup = 'Monitoraggio';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MonitorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MonitorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MonitorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChecksRelationManager::class,
            IncidentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonitors::route('/'),
            'create' => CreateMonitor::route('/create'),
            'view' => ViewMonitor::route('/{record}'),
            'edit' => EditMonitor::route('/{record}/edit'),
        ];
    }
}
