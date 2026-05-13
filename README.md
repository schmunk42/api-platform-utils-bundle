# schmunk42 API Platform Utils Bundle

Generic utilities for API Platform projects: UUID resolver, credential encryption, OpenAPI enhancements, and Hydra operations.

## Features

- **UUID Resolver**: Find entities by full or partial UUID (supports both binary and string UUID storage)
- **Partial UUID Item Provider**: Decorator that lets every `GET /resource/{id}` (and any custom item operation) accept a partial UUID — full UUIDs keep the fast indexed path
- **Credential Encryption**: Secure encryption/decryption using libsodium (XChaCha20-Poly1305)
- **Relation Field Schema Decorator**: Automatically adds x-* metadata to OpenAPI schemas for admin UI autocomplete
- **Hydra Operations Subscriber**: Enriches JSON-LD responses with operation metadata for better API discoverability

## Installation

### 1. Add to composer.json

Since this is an internal bundle in the `extensions/` directory, add autoloading:

```json
{
    "autoload": {
        "psr-4": {
            "Schmunk42\\ApiPlatformUtils\\": "extensions/api-platform-utils-bundle/src/"
        }
    }
}
```

Run composer dump-autoload:

```bash
composer dump-autoload
```

### 2. Register Bundle

Add to `config/bundles.php`:

```php
return [
    // ...
    Schmunk42\ApiPlatformUtils\Schmunk42ApiPlatformUtilsBundle::class => ['all' => true],
];
```

### 3. Configure

Create `config/packages/schmunk42_api_platform_utils.yaml`:

```yaml
schmunk42_api_platform_utils:
    # Credential Encryption (required)
    credential_encryption:
        key: '%env(base64:CREDENTIALS_ENCRYPTION_KEY)%'

    # Relation Field Schema Decorator (optional)
    relation_field_decorator:
        api_prefix: '/api'
        decoration_priority: 10
        label_property_candidates:
            - name
            - title
            - label
            - displayName

    # Hydra Operations (optional)
    hydra_operations:
        api_prefix: '/api'
        event_priority: -10

    # Partial UUID Item Provider (optional, off by default)
    partial_uuid_item_provider:
        enabled: true
```

### 4. Set Environment Variable

Add to `.env`:

```bash
# Generate with: docker compose exec php bin/console app:generate-encryption-key
CREDENTIALS_ENCRYPTION_KEY="base64-encoded-32-byte-key-here"
```

## Usage

### 1. UUID Resolver

Find entities by full or partial UUID:

```php
use Schmunk42\ApiPlatformUtils\Service\UuidResolver;

class MyCommand extends Command
{
    public function __construct(
        private readonly UuidResolver $uuidResolver
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Find by full UUID
        $entity = $this->uuidResolver->findByPartialUuid(
            Project::class,
            '550e8400-e29b-41d4-a716-446655440000'
        );

        // Find by partial UUID (first 8 characters)
        $entity = $this->uuidResolver->findByPartialUuid(
            Project::class,
            '550e8400'
        );

        if ($entity === null) {
            $output->writeln('Entity not found');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
```

**Features**:
- Supports binary UUID storage (BINARY(16) with Symfony Uid)
- Supports string UUID storage (CHAR(36) / GUID)
- Throws exception if partial UUID is ambiguous
- Automatically detects storage type from Doctrine metadata

### 2. Partial UUID Item Provider

Apply partial-UUID lookup project-wide without touching any operation
declaration. Once enabled, every item endpoint — standard `GET /resource/{id}`,
custom operations, sub-resources — accepts a partial UUID alongside the full
form.

Enable in `config/packages/schmunk42_api_platform_utils.yaml`:

```yaml
schmunk42_api_platform_utils:
    partial_uuid_item_provider:
        enabled: true
```

Resulting behavior:

```bash
# Full UUID — fast indexed path through the standard Doctrine provider
curl https://api.example.com/projects/550e8400-e29b-41d4-a716-446655440000

# Partial UUID — resolved via UuidResolver against the operation's resource class
curl https://api.example.com/projects/550e8400
```

**Routing logic** (preserves the fast path for production traffic):

| `{id}` value                                 | Path taken                                    |
| -------------------------------------------- | --------------------------------------------- |
| `Uuid` object or full RFC 4122 string        | Inner Doctrine provider (indexed `find()`)    |
| Non-UUID string of any length                | `UuidResolver::findByPartialUuid()`           |
| Missing                                      | Inner Doctrine provider                       |

**Logging**: successful partial resolutions are logged at `debug` level on
the `api_platform` channel — keep an eye on it to catch service-to-service
callers that should be sending full UUIDs.

**Caveats**:

- Partial-UUID matching is `LIKE 'prefix%'` on the indexed `id` column.
  Sub-millisecond at 10K rows; benchmark before relying on it past ~1M rows.
- Multi-match collisions raise the existing `UuidResolver` exception, which
  API Platform serializes as a 5xx response. Use full UUIDs in
  service-to-service traffic to avoid this.
- Off by default to avoid surprising existing consumers — opt in per project.

### 3. Credential Encryption

Securely encrypt/decrypt API credentials:

```php
use Schmunk42\ApiPlatformUtils\Service\CredentialEncryption;

class MyService
{
    public function __construct(
        private readonly CredentialEncryption $encryption
    ) {}

    public function store(array $credentials): string
    {
        // Encrypt credentials
        return $this->encryption->encrypt([
            'username' => 'user@example.com',
            'password' => 'secret123',
            'api_token' => 'token_abc123'
        ]);
    }

    public function retrieve(string $encryptedData): array
    {
        // Decrypt credentials
        try {
            return $this->encryption->decrypt($encryptedData);
        } catch (\RuntimeException $e) {
            // Decryption failed
            return [];
        }
    }
}
```

**Generate Encryption Key**:

```php
use Schmunk42\ApiPlatformUtils\Service\CredentialEncryption;

// Generate a new base64-encoded 32-byte key
$key = CredentialEncryption::generateKey();
echo "CREDENTIALS_ENCRYPTION_KEY=" . $key;
```

**Security Features**:
- Uses PHP's built-in libsodium (XChaCha20-Poly1305)
- Automatic nonce generation
- Memory cleanup with sodium_memzero
- Base64 encoding for storage

### 4. Relation Field Schema Decorator

Automatically enhances OpenAPI schemas for Doctrine relations. **No code required!**

**Example Entity**:

```php
use ApiPlatform\Metadata\ApiResource;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class Project
{
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    private ?Customer $customer = null;
}
```

**Generated OpenAPI Schema**:

```json
{
  "Project": {
    "properties": {
      "customer": {
        "type": ["string", "null"],
        "format": "iri-reference",
        "x-collection": "/api/admin/customers",
        "x-label-property": "name",
        "x-value-property": "@id",
        "x-search-property": "name",
        "x-resource-class": "Customer"
      }
    }
  }
}
```

**What it does**:
- Detects all Doctrine relations (ManyToOne, OneToOne, ManyToMany)
- Adds x-collection URL for autocomplete endpoints
- Infers label property from entity (checks: name, title, label, displayName)
- Only applies to INPUT schemas (forms, not responses)
- Configurable API prefix and label property candidates

**Use in Admin UI**:
The x-* extensions are used by admin UIs (like API Platform Admin) to render autocomplete dropdowns for relation fields.

### 5. Hydra Operations Subscriber

Automatically adds operation metadata to JSON-LD item responses. **No code required!**

**Before** (Standard JSON-LD response):

```json
{
  "@id": "/api/admin/projects/550e8400",
  "@type": "Project",
  "name": "My Project"
}
```

**After** (With hydra:operation):

```json
{
  "@id": "/api/admin/projects/550e8400",
  "@type": "Project",
  "name": "My Project",
  "hydra:operation": [
    {
      "@id": "/api/admin/projects/550e8400",
      "@type": "hydra:Operation",
      "method": "GET",
      "title": "Retrieves a Project resource",
      "returns": "Project"
    },
    {
      "@id": "/api/admin/projects/550e8400",
      "@type": "hydra:Operation",
      "method": "PUT",
      "title": "Replaces the Project resource",
      "expects": "Project",
      "returns": "Project"
    },
    {
      "@id": "/api/admin/projects/550e8400/health",
      "@type": "hydra:Operation",
      "method": "GET",
      "title": "Health",
      "returns": "Project"
    }
  ]
}
```

**What it does**:
- Adds complete list of available operations to each item response
- Includes standard CRUD operations and custom operations
- Generates human-readable titles for operations
- Adds expects/returns schema information
- Makes API self-documenting and discoverable

## Configuration Reference

```yaml
schmunk42_api_platform_utils:
    credential_encryption:
        enabled: true                    # Enable/disable credential encryption service
        key: '%env(base64:CREDENTIALS_ENCRYPTION_KEY)%'  # Required: 32-byte base64-encoded key

    relation_field_decorator:
        enabled: true                    # Enable/disable OpenAPI decorator
        api_prefix: '/api'               # API prefix for collection paths
        decoration_priority: 10          # Decorator priority (higher = runs first)
        label_property_candidates:       # Property names to check for labels (in order)
            - name
            - title
            - label
            - displayName

    hydra_operations:
        enabled: true                    # Enable/disable Hydra operations subscriber
        api_prefix: '/api'               # API prefix for operation paths
        event_priority: -10              # Event priority (negative = after API Platform)
```

## Requirements

- PHP 8.2+
- ext-sodium (built into PHP 7.2+)
- Symfony 7.0+
- API Platform 3.0+
- Doctrine ORM 2.0+ / 3.0+

## Architecture

### Bundle Structure

```
api-platform-utils-bundle/
├── src/
│   ├── Schmunk42ApiPlatformUtilsBundle.php      # Main bundle class
│   ├── DependencyInjection/
│   │   ├── Configuration.php                 # Configuration tree
│   │   └── Schmunk42ApiPlatformUtilsExtension.php  # DI extension
│   ├── Service/
│   │   ├── UuidResolver.php                  # UUID resolver service
│   │   └── CredentialEncryption.php          # Encryption service
│   ├── OpenApi/
│   │   └── RelationFieldSchemaDecorator.php  # OpenAPI decorator
│   └── EventSubscriber/
│       └── AddHydraOperationsSubscriber.php  # Event subscriber
├── config/
│   └── services.yaml                         # Service definitions
├── composer.json
├── README.md
└── CHANGELOG.md
```

### How It Works

1. **UuidResolver**: Uses Doctrine metadata to detect UUID storage type (binary vs string), then performs partial matching via SQL LIKE queries

2. **CredentialEncryption**: Wraps PHP's libsodium crypto_secretbox functions for symmetric encryption with automatic nonce handling

3. **RelationFieldSchemaDecorator**: Decorates API Platform's SchemaFactory, processes INPUT schemas, detects Doctrine associations, and adds x-* extensions

4. **AddHydraOperationsSubscriber**: Listens to ResponseEvent, checks for JSON-LD item responses, extracts all operations from resource metadata, and enriches response

## License

Proprietary - herzog kommunikation GmbH

## Credits

Developed for the ZA7 (Zentrales Agentursystem Version 7) project.
