<?php

namespace Liberu\Foundation\ModuleManagerFilament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Liberu\Foundation\ModuleManager\Exceptions\ModuleActivationRefused;
use Liberu\Foundation\ModuleManager\ModuleManagement as Modules;

/** Turns installed feature modules on and off. Installing and removing them is Composer's job. */
final class ModuleManagement extends Page implements HasTable
{
    use InteractsWithTable;

    /** Define this gate to decide who manages modules. Without it, nobody does. */
    public const ABILITY = 'manageModules';

    public const STATUS_LABELS = ['enabled' => 'Enabled', 'disabled' => 'Disabled', 'failed' => 'Failed'];

    protected static ?string $slug = 'modules';

    protected static ?string $title = 'Modules';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

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
            ->emptyStateIcon('heroicon-o-puzzle-piece')
            ->emptyStateHeading('No feature modules are installed')
            ->emptyStateDescription('Install one with composer require <package>, then enable it here.')
            ->columns([
                TextColumn::make('name')
                    ->label('Module')
                    ->weight('medium')
                    ->description(fn (array $record): ?string => filled($record['description'] ?? null) ? $record['description'] : null)
                    ->wrap(),
                TextColumn::make('version')->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'enabled' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'enabled' => 'heroicon-m-check-circle',
                        'failed' => 'heroicon-m-exclamation-triangle',
                        default => 'heroicon-m-minus-circle',
                    }),
                TextColumn::make('note')->label('')->wrap()->color('gray'),
            ])
            ->recordActions([
                Action::make('enable')
                    ->label('Enable')
                    ->button()
                    ->size(Size::Small)
                    ->color('success')
                    ->icon('heroicon-m-play')
                    ->visible(fn (array $record): bool => $record['changeable'] && $record['status'] === 'disabled')
                    ->action(fn (array $record) => $this->change($record['module'], true)),
                Action::make('disable')
                    ->label('Disable')
                    ->button()
                    ->size(Size::Small)
                    ->color('danger')
                    ->icon('heroicon-m-pause')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => "Disable {$record['name']}?")
                    ->modalDescription('Its features and menu entries are removed from the next request. The Composer package stays installed.')
                    ->modalSubmitActionLabel('Disable')
                    ->visible(fn (array $record): bool => $record['changeable'] && $record['status'] !== 'disabled')
                    ->action(fn (array $record) => $this->change($record['module'], false)),
            ]);
    }

    private function change(string $module, bool $enable): void
    {
        abort_unless(self::canAccess(), 403);

        try {
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
            $note = $module['problems'] !== [] ? implode(' ', $module['problems']) : $module['locked'];

            $rows[$name] = $module + ['changeable' => $module['locked'] === null, 'note' => $note];
        }

        return $rows;
    }
}
