<?php

namespace Liberu\Foundation\ModuleManagerFilament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Liberu\Foundation\ModuleManager\Exceptions\ModuleActivationRefused;
use Liberu\Foundation\ModuleManager\ModuleManagement as Modules;

/** Turns installed modules on and off. Installing and removing them is Composer's job. */
final class ModuleManagement extends Page implements HasTable
{
    use InteractsWithTable;

    /** Define this gate to decide who manages modules. Without it, nobody does. */
    public const ABILITY = 'manageModules';

    private const SELF = 'module-manager-filament';

    protected static ?string $slug = 'modules';

    protected static ?string $title = 'Modules';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && Gate::has(self::ABILITY) && Gate::forUser($user)->allows(self::ABILITY);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Module'),
                TextColumn::make('version'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'enabled' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('note')->label('')->wrap()->color('gray'),
            ])
            ->recordActions([
                Action::make('enable')
                    ->icon('heroicon-o-play')
                    ->visible(fn (array $record): bool => $record['changeable'] && $record['status'] === 'disabled')
                    ->action(fn (array $record) => $this->change($record['module'], true)),
                Action::make('disable')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('It stops registering from the next request. The Composer package stays installed.')
                    ->visible(fn (array $record): bool => $record['changeable'] && $record['status'] !== 'disabled')
                    ->action(fn (array $record) => $this->change($record['module'], false)),
            ]);
    }

    private function change(string $module, bool $enable): void
    {
        abort_unless(self::canAccess(), 403);

        try {
            abort_if($module === self::SELF, 403);
            $enable ? app(Modules::class)->enable($module) : app(Modules::class)->disable($module);
            Notification::make()->title($enable ? 'Module enabled' : 'Module disabled')->body('This takes effect from the next request.')->success()->send();
        } catch (ModuleActivationRefused $exception) {
            Notification::make()->title('Not changed')->body($exception->getMessage())->danger()->send();
        }

        $this->flushCachedTableRecords();
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        $rows = [];

        foreach (app(Modules::class)->modules() as $name => $module) {
            $locked = $name === self::SELF ? 'This page is provided by this module.' : $module['locked'];

            $rows[$name] = $module + ['changeable' => $locked === null, 'note' => $module['problems'] !== [] ? implode(' ', $module['problems']) : $locked];
        }

        return $rows;
    }
}
