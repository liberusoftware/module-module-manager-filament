# Changelog

## 1.11.0 - 2026-11-13

- The Modules page moves from the Operations navigation group to Settings. Nothing else changes.

## 1.10.0 - 2026-11-11

- Remove the Foundation Operations page (`/foundation-operations`) and its navigation entry. The Modules page is now the only page, and the `viewModuleDiagnostics` gate and the optional `liberusoftware/observability` suggestion are no longer used.

## 1.9.0 - 2026-11-10

- The Modules page lists only feature modules, the ones whose manifest declares `"module_type": "feature"`; infrastructure no longer appears. Requires `liberusoftware/module-manager` ^1.9.
- Each module's description from its manifest shows under its name.
- Enable and Disable are solid buttons of the same size, and status is an Enabled, Disabled or Failed badge.
- With no feature module installed, the table says so and how to install one with Composer.

## 1.8.0 - 2026-11-10

- Add the Modules page: installed modules with their version and status, and working Enable and Disable actions. Access follows the host's `manageModules` gate and is denied when it is not defined.

## 1.7.0 - 2026-11-06

- Authorize the page through a `viewModuleDiagnostics` gate the host defines, and stop requiring `liberusoftware/observability`. Without the gate, a user implementing `ObservabilityActor` still gets in through `isAdmin()`, so existing hosts keep working; with neither, access is denied.

## 1.6.0 - 2026-11-06

- Show each module's kind, status and problems, including modules that failed to load, when the installed `module-manager` provides `ModuleInventory`. With an older release the page is unchanged.

## 1.2.2 - 2026-08-06

- Split CI into the three workflows `CONFORMANCE.md` §3.9 asks for, rather than one calling all
  three. Only `tests` runs on every push; installing from nothing and resolving the lowest allowed
  dependencies are release questions and now run on tags. One caller meant four jobs per push, and
  a 44-repository publish sweep stalled the organisation's Actions queue.

## 1.2.0 - 2026-08-06

- Move the boundary suites to `liberusoftware/package-testbench`: this repository no longer
  ships a test bootstrap or its own metadata and provider tests, and its `phpunit.xml` points
  at the shipped suite instead. A new boundary rule is now a testbench release rather than a
  change made by hand in every package repository.
- Call the reusable workflows in `liberusoftware/.github` rather than restating CI setup.

## 1.1.0 - 2026-08-05

- Conformance wave: package and namespace renames, manifest categories and `default_enabled`
  settled against `liberusoftware/documentation`. See the host's `docs/CONFORMANCE.md` §3.

## 1.1.0 - 2026-08-05

- Extract the foundation operations admin-panel plugin and page from `foundation-filament`.
