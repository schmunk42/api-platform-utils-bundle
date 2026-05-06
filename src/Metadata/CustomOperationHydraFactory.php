<?php
// file generated with AI assistance: Claude Code - 2026-05-06 14:30:00 UTC

declare(strict_types=1);

namespace Schmunk42\ApiPlatformUtils\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Auto-detects Custom Operations (URIs that are neither /resource nor
 * /resource/{id}) and marks them with explicit Hydra metadata so JSON-LD
 * clients can discover them via @type: schema:Action instead of the default
 * schema:CreateAction that API Platform would assign to any POST.
 *
 * Auto-detection rules (URI-based, no annotation needed):
 *   /resource              → standard collection (skip)
 *   /resource/{param}      → standard item       (skip)
 *   anything else          → custom operation, marked with schema:Action
 *
 * The factory decorates the ResourceMetadataCollectionFactory chain at low
 * priority (default 100), so URI templates and operation names are already
 * resolved when this layer runs.
 *
 * Hydra context written for every detected custom operation:
 *   - @type:        ["hydra:Operation", "schema:Action"]
 *   - hydra:title:  literal value of operation `name`
 *   - expects:      input class short name, or owl:Nothing if input=false
 *   - returns:      output class short name, or owl:Nothing if no output
 *   - hydra:description: from operation `description` if set
 *
 * Existing values in `hydraContext` of the operation are preserved.
 */
final class CustomOperationHydraFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $i => $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();
            if (null === $operations || 0 === \count($operations)) {
                continue;
            }

            $rebuilt = [];
            foreach ($operations as $name => $operation) {
                $rebuilt[$name] = $this->isCustomOperation($operation)
                    ? $this->markAsCustom($operation)
                    : $operation;
            }

            $collection[$i] = $resourceMetadata->withOperations(new Operations($rebuilt));
        }

        return $collection;
    }

    private function isCustomOperation(Operation $operation): bool
    {
        if (!$operation instanceof HttpOperation) {
            return false;
        }

        $template = trim($operation->getUriTemplate() ?? '', '/');
        if ('' === $template) {
            return false;
        }

        $segments = explode('/', $template);
        $count = \count($segments);

        // /resource
        if (1 === $count) {
            return false;
        }

        // /resource/{id}
        if (2 === $count && str_starts_with($segments[1], '{')) {
            return false;
        }

        return true;
    }

    private function markAsCustom(HttpOperation $operation): HttpOperation
    {
        $existing = $operation->getHydraContext() ?? [];
        $title = $operation->getName() ?? $this->fallbackTitleFromUri($operation);
        $output = $operation->getOutput() ?? [];
        $outputClass = \is_array($output) && \array_key_exists('class', $output) ? $output['class'] : null;
        $input = $operation->getInput() ?? [];
        $inputClass = \is_array($input) && \array_key_exists('class', $input) ? $input['class'] : null;

        $context = $existing + [
            '@type' => ['hydra:Operation', 'schema:Action'],
            'hydra:title' => $title,
            'expects' => null === $inputClass ? 'owl:Nothing' : $this->classShortName($inputClass),
            'returns' => null === $outputClass ? 'owl:Nothing' : $this->classShortName($outputClass),
        ];

        if (null !== $operation->getDescription() && !isset($context['hydra:description'])) {
            $context['hydra:description'] = $operation->getDescription();
        }

        return $operation->withHydraContext($context);
    }

    private function fallbackTitleFromUri(HttpOperation $operation): string
    {
        $template = trim($operation->getUriTemplate() ?? '', '/');
        $segments = array_values(array_filter(
            explode('/', $template),
            static fn (string $s) => '' !== $s && !str_starts_with($s, '{'),
        ));

        return strtolower($operation->getMethod() ?? 'POST').'_'.implode('_', $segments);
    }

    private function classShortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : substr($fqcn, $pos + 1);
    }
}
