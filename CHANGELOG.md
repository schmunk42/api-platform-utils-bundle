# Changelog

All notable changes to the schmunk42 API Platform Utils Bundle will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **PartialUuidItemProvider** decorator for project-wide partial-UUID item lookups
  - Decorates `api_platform.doctrine.orm.state.item_provider`
  - Full UUIDs (Uuid object or RFC 4122 string) take the fast indexed path through the inner provider — no overhead beyond a single regex match
  - Non-UUID `{id}` URI variables are resolved via `UuidResolver::findByPartialUuid()` against the operation's own resource class (`$operation->getClass()`)
  - Multi-match collisions surface via the existing `UuidResolver` exception path
  - Successful partial resolutions are logged at `debug` level on the `api_platform` channel — useful for spotting unintended partial-UUID traffic in production
  - Off by default (`partial_uuid_item_provider.enabled: false`) to avoid surprising existing consumers; opt in per project
  - Intended as a convenience layer for CLI workflows, ad-hoc curl, and admin debugging — service-to-service traffic should continue to use full UUIDs
- **CustomOperationHydraFactory** for auto-detecting custom operations in the Hydra documentation
  - Decorates `ResourceMetadataCollectionFactory` (default priority 100)
  - URI-based detection: anything other than `/resource` and `/resource/{id}` is a custom operation
  - Marks each detected operation with `@type=["hydra:Operation","schema:Action"]` instead of API Platform's default `schema:CreateAction` for POSTs
  - Sets `hydra:title` literally to the operation `name`, derives `expects`/`returns` from input/output metadata
  - Preserves existing `hydraContext` values; no entity-side annotation needed
  - Configurable via `custom_operation_hydra.enabled` and `decoration_priority`
  - Hydra clients can filter custom operations with: `select(@type contains schema:Action and not contains schema:CreateAction)`

## [1.0.0] - 2025-11-22

### Added
- Initial release of schmunk42/api-platform-utils-bundle
- **UuidResolver** service for finding entities by full or partial UUID
  - Supports both binary UUID storage (Symfony Uid) and string UUID storage (GUID)
  - Intelligent partial matching with ambiguity detection
  - Automatic storage type detection from Doctrine metadata
- **CredentialEncryption** service for secure credential storage
  - Uses libsodium XChaCha20-Poly1305 encryption
  - Automatic nonce generation
  - Memory cleanup with sodium_memzero
  - Static generateKey() helper method
- **RelationFieldSchemaDecorator** for OpenAPI enhancements
  - Automatically adds x-collection, x-label-property, x-value-property, x-search-property, x-resource-class
  - Detects all Doctrine relations (ManyToOne, OneToOne, ManyToMany)
  - Infers label properties from entity structure
  - Configurable API prefix and label property candidates
  - Only applies to INPUT schemas (forms)
- **AddHydraOperationsSubscriber** for JSON-LD response enrichment
  - Adds hydra:operation array to all item responses
  - Includes standard CRUD and custom operations
  - Human-readable operation titles
  - expects/returns schema information
  - Makes API self-documenting and discoverable
- Configuration system with schmunk42_api_platform_utils.yaml
- Bundle auto-configuration via DependencyInjection extension
- Comprehensive documentation with usage examples

### Extracted from ZA7
The following components were extracted and refactored from the ZA7 project:
- `App\Service\UuidResolver` → `Schmunk42\ApiPlatformUtils\Service\UuidResolver`
- `App\Service\CredentialEncryption` → `Schmunk42\ApiPlatformUtils\Service\CredentialEncryption`
- `App\OpenApi\RelationFieldSchemaDecorator` → `Schmunk42\ApiPlatformUtils\OpenApi\RelationFieldSchemaDecorator`
- `App\EventSubscriber\AddHydraOperationsSubscriber` → `Schmunk42\ApiPlatformUtils\EventSubscriber\AddHydraOperationsSubscriber`

### Changed
- RelationFieldSchemaDecorator: Made API prefix configurable (was hardcoded to '/api')
- RelationFieldSchemaDecorator: Made label property candidates configurable (was hardcoded constant)
- AddHydraOperationsSubscriber: Made API prefix configurable (was hardcoded to '/api')
- Both decorators now use constructor injection for configuration parameters

### Technical Details
- Created as internal bundle in `extensions/api-platform-utils-bundle/`
- Fully tested with ZA7's existing test suite (26 tests passing)
- Zero breaking changes to existing functionality
- All original features preserved

---

Generated with AI assistance: Claude Code - 2025-11-22
