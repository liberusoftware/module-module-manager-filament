<?php

namespace Liberu\Foundation\ModuleManagerFilament\Pages;

use Filament\Pages\Page;
use Liberu\Foundation\ModuleManager\ModuleInventory;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\Observability\Contracts\ObservabilityActor;

final class FoundationOperations extends Page
{
    protected string $view = 'module-manager-filament::pages.foundation-operations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Foundation Operations';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public array $modules = [];

    public function mount(ModuleRegistry $registry): void
    {
        $this->modules = array_map(fn ($manifest) => $manifest->toArray(), $registry->all());

        // Older module-manager releases have no inventory, and the page keeps working without it.
        if (! class_exists(ModuleInventory::class) || ! app()->bound(ModuleInventory::class)) {
            return;
        }

        foreach (app(ModuleInventory::class)->rows() as $name => $row) {
            $this->modules[$name] = ($this->modules[$name] ?? ['display_name' => $name, 'version' => $row['version'], 'capabilities' => []])
                + ['kind' => $row['kind'], 'status' => $row['status'], 'problems' => $row['problems']];
        }
    }

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof ObservabilityActor && $actor->isAdmin();
    }
}
