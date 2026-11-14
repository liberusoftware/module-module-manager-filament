<?php

use Illuminate\Auth\GenericUser;
use Liberu\Foundation\ModuleManager\Manifest;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\ModuleManagerFilament\Pages\FoundationOperations;
use Liberu\Foundation\Observability\Contracts\ObservabilityActor;

beforeEach(function () {
    if (! class_exists(FoundationOperations::class)) {
        $this->markTestSkipped('The host does not provide the optional operations page.');
    }
});

it('denies operations access to guests and actors without the observability contract', function () {
    expect(FoundationOperations::canAccess())->toBeFalse();

    $this->actingAs(new GenericUser(['id' => 1]));

    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('requires administrative authorization from the host observability actor', function () {
    $actor = new class(['id' => 1, 'admin' => false]) extends GenericUser implements ObservabilityActor
    {
        public function isAdmin(): bool
        {
            return $this->attributes['admin'];
        }
    };
    $this->actingAs($actor);

    expect(FoundationOperations::canAccess())->toBeFalse();

    $actor->admin = true;

    expect(FoundationOperations::canAccess())->toBeTrue();
});

it('projects registry manifests into the operations page without querying operational tables', function () {
    $manifest = Manifest::fromFile(dirname(__DIR__, 2).'/module.json');
    $page = new FoundationOperations();
    $page->mount(new ModuleRegistry([$manifest->name() => $manifest]));

    expect($page->modules)->toHaveKey($manifest->name())
        ->and($page->modules[$manifest->name()]['name'])->toBe($manifest->name())
        ->and($page->modules[$manifest->name()]['version'])->toBe($manifest->version())
        ->and($page->modules[$manifest->name()]['capabilities'])->toBe($manifest->capabilities());
});
