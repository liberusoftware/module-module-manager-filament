<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Liberu\Foundation\ModuleManager\Exceptions\ModuleActivationRefused;
use Liberu\Foundation\ModuleManager\ModuleManagement as Modules;
use Liberu\Foundation\ModuleManagerFilament\ModuleManagerFilamentPlugin;
use Liberu\Foundation\ModuleManagerFilament\Pages\ModuleManagement;

it('is denied to guests, and to everyone when the host defines no gate', function () {
    expect(ModuleManagement::canAccess())->toBeFalse();

    $this->actingAs(new User());
    expect(ModuleManagement::canAccess())->toBeFalse();
});

it('follows the host gate', function () {
    Gate::define(ModuleManagement::ABILITY, fn (User $user) => $user->getAttribute('ops') === true);

    $this->actingAs((new User())->forceFill(['ops' => true]));
    expect(ModuleManagement::canAccess())->toBeTrue();

    $this->actingAs((new User())->forceFill(['ops' => false]));
    expect(ModuleManagement::canAccess())->toBeFalse();
});

it('is served at /modules and registered by the plugin', function () {
    expect(ModuleManagement::getSlug())->toBe('modules');

    $source = (string) file_get_contents((new ReflectionClass(ModuleManagerFilamentPlugin::class))->getFileName());
    expect($source)->toContain('ModuleManagement::class');
});

it('describes each module with its manifest description', function () {
    $source = (string) file_get_contents((new ReflectionClass(ModuleManagement::class))->getFileName());

    expect($source)->toContain("->description(fn (array \$record): ?string => filled(\$record['description'] ?? null)");
});

it('labels every status the manager reports', function () {
    expect(ModuleManagement::STATUS_LABELS)->toBe(['enabled' => 'Enabled', 'disabled' => 'Disabled', 'failed' => 'Failed']);
});

it('uses the manager for every change and has no store or application concepts', function () {
    expect(class_exists(Modules::class))->toBeTrue()->and(class_exists(ModuleActivationRefused::class))->toBeTrue();

    $source = strtolower((string) file_get_contents((new ReflectionClass(ModuleManagement::class))->getFileName()));
    expect($source)->not->toContain('store')->not->toContain('team')->not->toContain('ecommerce');
});
