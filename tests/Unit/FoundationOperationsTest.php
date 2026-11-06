<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Liberu\Foundation\ModuleManager\Manifest;
use Liberu\Foundation\ModuleManager\ModuleInventory;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\ModuleManagerFilament\Pages\FoundationOperations;

function operationsManifest(string $name): Manifest
{
    $dir = sys_get_temp_dir().'/operations-'.bin2hex(random_bytes(5));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/module.json', json_encode([
        'name' => $name, 'display_name' => ucfirst($name), 'description' => 'Test', 'version' => '1.0.0', 'category' => 'capability',
        'provider' => ServiceProvider::class, 'requires' => ['packages' => []], 'capabilities' => ['test.'.$name],
        'features' => ['Test'], 'default_enabled' => true,
    ], JSON_THROW_ON_ERROR));

    return Manifest::fromFile($dir.'/module.json');
}

beforeEach(function () {
    View::addNamespace('module-manager-filament', dirname(__DIR__, 2).'/resources/views');
});

it('lists installed modules from the registry alone', function () {
    app()->forgetInstance(ModuleInventory::class);
    $page = new FoundationOperations();

    $page->mount(new ModuleRegistry(['good' => operationsManifest('good')]));

    $html = view('module-manager-filament::pages.module-table', ['modules' => $page->modules])->render();
    expect($page->modules['good']['display_name'])->toBe('Good')
        ->and($page->modules['good'])->not->toHaveKey('status')
        ->and($html)->toContain('Good', 'test.good')
        ->and($html)->not->toContain('Problems');
});

it('adds status, and shows modules that failed to load, when the manager provides an inventory', function () {
    $good = operationsManifest('good');
    $off = operationsManifest('off');
    app()->instance(ModuleInventory::class, new ModuleInventory(
        ['good' => $good, 'off' => $off],
        ['good' => $good],
        ['broken' => ['Syntax error']],
        ['good'],
    ));
    $page = new FoundationOperations();

    $page->mount(new ModuleRegistry(['good' => $good, 'off' => $off]));

    expect($page->modules['good'])->toMatchArray(['kind' => 'required', 'status' => 'booted'])
        ->and($page->modules['off'])->toMatchArray(['kind' => 'optional', 'status' => 'disabled'])
        ->and($page->modules['broken'])->toMatchArray(['status' => 'invalid', 'problems' => ['Syntax error']]);

    $html = view('module-manager-filament::pages.module-table', ['modules' => $page->modules])->render();
    expect($html)->toContain('Problems', 'booted', 'invalid', 'Syntax error');
})->skip(! class_exists(ModuleInventory::class), 'Needs a module-manager with ModuleInventory.');
