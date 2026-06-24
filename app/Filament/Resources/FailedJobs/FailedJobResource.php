<?php

namespace App\Filament\Resources\FailedJobs;

use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Filament\Resources\FailedJobs\Pages\ViewFailedJob;
use App\Filament\Resources\FailedJobs\Schemas\FailedJobInfolist;
use App\Filament\Resources\FailedJobs\Tables\FailedJobsTable;
use App\Models\FailedJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FailedJobResource extends Resource
{
    protected static ?string $model = FailedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Failed jobs';

    protected static ?string $modelLabel = 'Failed job';

    protected static ?string $pluralModelLabel = 'Failed jobs';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FailedJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FailedJobsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailedJobs::route('/'),
            'view' => ViewFailedJob::route('/{record}'),
        ];
    }
}
