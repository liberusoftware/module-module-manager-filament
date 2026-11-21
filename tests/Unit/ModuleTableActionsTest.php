<?php

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Composer\InstalledVersions;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Panel;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Liberu\Foundation\ModuleManager\Manifest;
use Liberu\Foundation\ModuleManager\ModuleActivations;
use Liberu\Foundation\ModuleManager\ModuleInventory;
use Liberu\Foundation\ModuleManager\ModuleManagement as Modules;
use Liberu\Foundation\ModuleManager\ModuleValidator;
use Liberu\Foundation\ModuleManagerFilament\ModuleManagerFilamentPlugin;
use Liberu\Foundation\ModuleManagerFilament\Pages\ModuleManagement;
use Livewire\Livewire;

final class TableFixtureProvider extends ServiceProvider {}

beforeEach(function () {
    $this->fixtureRoot = sys_get_temp_dir().'/module-table-'.bin2hex(random_bytes(6));
    mkdir($this->fixtureRoot, 0777, true);
    $this->originalComposer = require dirname(__DIR__, 2).'/vendor/composer/installed.php';
    $composer = $this->originalComposer;
    $manifests = [];
    foreach (['alpha' => false, 'beta' => true, 'broken' => true, 'locked' => true] as $name => $enabled) {
        $dir = $this->fixtureRoot.'/'.$name;
        mkdir($dir);
        file_put_contents($dir.'/composer.json', json_encode(['name' => 'fixture/'.$name, 'type' => 'liberu-module', 'extra' => ['liberu' => ['name' => $name]]]));
        file_put_contents($dir.'/module.json', json_encode([
            'name' => $name, 'display_name' => ucfirst($name), 'description' => $name === 'beta' ? '' : 'Fixture '.$name,
            'version' => '1.0.0', 'category' => 'capability', 'provider' => TableFixtureProvider::class,
            'requires' => ['packages' => []], 'capabilities' => [], 'features' => ['Fixture'],
            'default_enabled' => $enabled, 'module_type' => 'feature',
        ]));
        $manifests[$name] = Manifest::fromFile($dir.'/module.json');
        $composer['versions']['fixture/'.$name] = ['pretty_version' => '1.0.0', 'version' => '1.0.0.0', 'reference' => null, 'type' => 'liberu-module', 'install_path' => $dir, 'aliases' => [], 'dev_requirement' => false];
    }
    InstalledVersions::reload($composer);
    Schema::create('module_activations', function ($table) {
        $table->string('module')->primary();
        $table->boolean('enabled');
        $table->timestamps();
    });
    $this->inventory = new ModuleInventory($manifests, [], ['broken' => ['Fixture failure']], ['locked']);
    $packages = new PackageManifest(new Filesystem(), base_path(), $this->fixtureRoot.'/packages.php');
    $packages->manifest = [];
    app()->instance(Modules::class, new Modules($this->inventory, app(ModuleActivations::class), new ModuleValidator(), $packages, [], []));
    Gate::define(ModuleManagement::ABILITY, fn (User $user) => $user->getAttribute('ops') === true);
    $this->actingAs((new User())->forceFill(['id' => 1, 'ops' => true]));
    foreach ([
        BladeIconsServiceProvider::class,
        BladeHeroiconsServiceProvider::class,
        SupportServiceProvider::class,
        ActionsServiceProvider::class,
        SchemasServiceProvider::class,
        FormsServiceProvider::class,
        InfolistsServiceProvider::class,
        NotificationsServiceProvider::class,
        TablesServiceProvider::class,
    ] as $provider) {
        app()->register($provider);
    }
    $panel = Panel::make()->id('test')->path('test')->plugin(ModuleManagerFilamentPlugin::make());
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
});

afterEach(function () {
    InstalledVersions::reload($this->originalComposer);
    (new Filesystem())->deleteDirectory($this->fixtureRoot);
});

it('renders installed module states and only exposes permitted transitions', function () {
    Livewire::test(ModuleManagement::class)
        ->assertSuccessful()
        ->assertSee('Fixture failure')
        ->assertSee('This application requires this module.')
        ->assertTableActionVisible('enable', 'alpha')
        ->assertTableActionHidden('disable', 'alpha')
        ->assertTableActionVisible('disable', 'beta')
        ->assertTableActionHidden('enable', 'beta')
        ->assertTableActionVisible('disable', 'broken')
        ->assertTableActionHidden('enable', 'locked')
        ->assertTableActionHidden('disable', 'locked');
});

it('enables and disables installed modules through the visible table actions', function () {
    Livewire::test(ModuleManagement::class)
        ->callTableAction('enable', 'alpha')
        ->assertTableActionVisible('disable', 'alpha')
        ->assertTableActionHidden('enable', 'alpha');

    expect(app(ModuleActivations::class)->all())->toHaveKey('alpha', true);

    Livewire::test(ModuleManagement::class)
        ->callTableAction('disable', 'beta')
        ->assertTableActionVisible('enable', 'beta')
        ->assertTableActionHidden('disable', 'beta');

    expect(app(ModuleActivations::class)->all())->toHaveKey('beta', false);
});

it('shows an activation refusal without persisting an invalid selection', function () {
    $this->inventory->problems['alpha'] = ['Fixture invalid metadata'];
    // A malformed provider is discovered again by the manager before activation.
    $path = $this->fixtureRoot.'/alpha/module.json';
    $data = json_decode(file_get_contents($path), true);
    $data['provider'] = 'MissingFixtureProvider';
    file_put_contents($path, json_encode($data));
    $manifests = $this->inventory->manifests;
    $manifests['alpha'] = Manifest::fromFile($path);
    $inventory = new ModuleInventory($manifests, [], ['alpha' => ['Fixture invalid metadata']], ['locked']);
    $packages = new PackageManifest(new Filesystem(), base_path(), $this->fixtureRoot.'/packages.php');
    $packages->manifest = [];
    app()->instance(Modules::class, new Modules($inventory, app(ModuleActivations::class), new ModuleValidator(), $packages, [], []));

    Livewire::test(ModuleManagement::class)->callTableAction('enable', 'alpha');

    expect(app(ModuleActivations::class)->all())->not->toHaveKey('alpha');
    Notification::assertNotified('Not changed');
});

it('denies a mounted action if the host revokes authorization before submission', function () {
    $page = Livewire::test(ModuleManagement::class);
    Gate::define(ModuleManagement::ABILITY, fn () => false);

    $page->call('mountAction', 'enable', [], ['table' => true, 'recordKey' => 'alpha'])->assertForbidden();

    expect(app(ModuleActivations::class)->all())->toBe([]);
});

it('registers the page through its plugin public API', function () {
    $plugin = ModuleManagerFilamentPlugin::make();
    $panel = Panel::make()->id('plugin-test');
    $plugin->register($panel);
    $plugin->boot($panel);

    expect($plugin->getId())->toBe('liberu-module-manager')
        ->and($panel->getPages())->toContain(ModuleManagement::class);
});
