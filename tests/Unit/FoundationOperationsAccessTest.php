<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Liberu\Foundation\ModuleManager\Manifest;
use Liberu\Foundation\ModuleManager\ModuleRegistry;
use Liberu\Foundation\ModuleManagerFilament\Pages\FoundationOperations;
use Liberu\Foundation\Observability\Contracts\ObservabilityActor;

function observabilityAdmin(bool $admin): User
{
    if (! interface_exists(ObservabilityActor::class)) {
        require_once dirname(__DIR__).'/Fixtures/ObservabilityActor.php';
    }

    return new class($admin) extends User implements ObservabilityActor
    {
        public function __construct(private bool $admin = false)
        {
            parent::__construct();
        }

        public function isAdmin(): bool
        {
            return $this->admin;
        }
    };
}

it('is denied to guests', function () {
    Gate::define(FoundationOperations::ABILITY, fn () => true);

    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('is denied to everyone when the host defines no gate and observability is absent', function () {
    $this->actingAs(new User());

    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('follows the host gate', function () {
    Gate::define(FoundationOperations::ABILITY, fn (User $user) => $user->getAttribute('ops') === true);

    $this->actingAs((new User())->forceFill(['ops' => true]));
    expect(FoundationOperations::canAccess())->toBeTrue();

    $this->actingAs((new User())->forceFill(['ops' => false]));
    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('lets the gate overrule an observability admin', function () {
    Gate::define(FoundationOperations::ABILITY, fn () => false);
    $this->actingAs(observabilityAdmin(true));

    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('falls back to observability when the host defines no gate', function () {
    $this->actingAs(observabilityAdmin(true));
    expect(FoundationOperations::canAccess())->toBeTrue();

    $this->actingAs(observabilityAdmin(false));
    expect(FoundationOperations::canAccess())->toBeFalse();
});

it('resolves with only the module manager enabled', function () {
    $registry = new ModuleRegistry(['module-manager-filament' => Manifest::fromFile(dirname(__DIR__, 2).'/module.json')]);

    expect(array_map(fn (Manifest $m) => $m->name(), $registry->resolve(['module-manager-filament'])))->toBe(['module-manager-filament']);
});
