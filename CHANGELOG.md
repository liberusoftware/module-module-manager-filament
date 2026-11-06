# Changelog

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
