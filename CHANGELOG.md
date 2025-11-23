# Changelog

All notable changes to the dmstr API Platform Utils Bundle will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-11-22

### Added
- Initial release of dmstr/api-platform-utils-bundle
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
- Configuration system with dmstr_api_platform_utils.yaml
- Bundle auto-configuration via DependencyInjection extension
- Comprehensive documentation with usage examples

### Extracted from ZA7
The following components were extracted and refactored from the ZA7 project:
- `App\Service\UuidResolver` → `Dmstr\ApiPlatformUtils\Service\UuidResolver`
- `App\Service\CredentialEncryption` → `Dmstr\ApiPlatformUtils\Service\CredentialEncryption`
- `App\OpenApi\RelationFieldSchemaDecorator` → `Dmstr\ApiPlatformUtils\OpenApi\RelationFieldSchemaDecorator`
- `App\EventSubscriber\AddHydraOperationsSubscriber` → `Dmstr\ApiPlatformUtils\EventSubscriber\AddHydraOperationsSubscriber`

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
