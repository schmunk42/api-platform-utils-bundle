# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

A standalone Symfony bundle (`schmunk42/api-platform-utils-bundle`, MIT) that ships four cross-cutting utilities for API Platform projects:

1. `Service\UuidResolver` — finds entities by full or partial UUID (handles both `BINARY(16)` and `CHAR(36)` storage).
2. `Service\CredentialEncryption` — libsodium (XChaCha20-Poly1305) symmetric encryption for stored credentials.
3. `OpenApi\RelationFieldSchemaDecorator` — decorates `api_platform.json_schema.schema_factory` and adds `x-*` extensions on INPUT schemas for Doctrine relations (used by admin UIs for autocomplete).
4. Hydra metadata enrichment, split across three collaborators that must stay coherent:
   - `Metadata\CustomOperationHydraFactory` (decorates `ResourceMetadataCollectionFactory`, priority 100) — auto-detects custom operations by URI shape and writes their `hydraContext` (`@type=[hydra:Operation,schema:Action]`, `hydra:title`, `hydra:expects/returns`, `@id` or `hydra:uriTemplate`).
   - `EventSubscriber\HydraDocumentationSubscriber` — patches `/api/docs.jsonld` (renames `expects/returns` → `hydra:expects/hydra:returns`, adds URL carriers on standard CRUD operations).
   - `EventSubscriber\AddHydraOperationsSubscriber` — adds `hydra:operation` to JSON-LD **item** responses (GET on URIs containing `{id}`).
5. `State\PartialUuidItemProvider` — opt-in decorator on `api_platform.doctrine.orm.state.item_provider` that routes non-full-UUID `{id}` values through `UuidResolver`. Off by default (see `partial_uuid_item_provider.enabled`); the standard fast path is preserved for full UUIDs.

The bundle is consumed by the parent project ZA7 (see `../../../../CLAUDE.md` for the project-wide operation/CLI conventions this bundle implements).

## Architecture conventions

- **Standard Symfony bundle layout**: `ApiPlatformUtilsBundle` → `DependencyInjection\{Configuration,ApiPlatformUtilsExtension}` → `config/services.yaml`. All services are declared with `autowire: false, autoconfigure: false, public: false` — declare every argument explicitly.
- **Config alias**: `schmunk42_api_platform_utils`. Every feature has its own subtree with `enabled` + feature-specific options. Add new options to `Configuration.php` AND expose them as container parameters in `ApiPlatformUtilsExtension::load()` (parameter naming: `schmunk42_api_platform_utils.<feature>.<option>`).
- **Optional services** load conditionally from a separate YAML file inside the extension (see `services_partial_uuid.yaml`, gated by `$config['partial_uuid_item_provider']['enabled']`). Use the same pattern when adding off-by-default decorators that would change behavior for existing consumers.
- **API prefix is configurable per feature** (`relation_field_decorator.api_prefix`, `hydra_operations.api_prefix`, `hydra_documentation.api_prefix`, `custom_operation_hydra.api_prefix`) and injected via constructor — never hardcode `/api`.
- **Decorator priorities matter**: `CustomOperationHydraFactory` runs at priority 100, after `uri_template` (500) and `operation_name` (200), so URI templates and operation names are already resolved.
- **Custom-operation detection is URI-based, not annotation-based**: trim `uriTemplate` of `/`, split on `/`. 1 segment → standard collection; 2 segments where the second starts with `{` → standard item; everything else → custom. This rule is duplicated implicitly across factory + subscribers — keep it in sync.
- **Operation `name` is the source of truth for `hydra:title` and OpenAPI `operationId`** (see ZA7 CLAUDE.md “Operation Naming”). The factory writes `name` verbatim into `hydra:title`.
- **`hydraContext` is merged, not overwritten** (`$existing + [...]`) — never clobber values an entity has already set.
- **URL carrier rule** for custom operations: emit `@id` when the URI has no `{placeholder}`, `hydra:uriTemplate` when it does. Never both, never neither.

## File header convention

Every PHP/YAML file in this bundle carries an AI-assistance header on line 1 or 2 (e.g. `// file generated with AI assistance: Claude Code - YYYY-MM-DD`). Match the format when adding new files; update the timestamp when meaningfully rewriting one.

## Tests

There is no PHPUnit suite in this repository. Verification happens in the host project (ZA7); the CHANGELOG entry for 1.0.0 notes “Fully tested with ZA7's existing test suite (26 tests passing).” When changing behavior here, run the tests in the consuming project rather than expecting a local `composer test`.

## Requirements

PHP 8.2+, `ext-sodium`, Symfony 7.0+, API Platform 3 or 4, Doctrine ORM 2 or 3. The `composer.json` allows both API Platform 3 and 4 via `^3.0|^4.0` — keep new code compatible with both.
