<?php
// file generated with AI assistance: Claude Code - 2025-11-22

declare(strict_types=1);

namespace Schmunk42\ApiPlatformUtils;

use Schmunk42\ApiPlatformUtils\DependencyInjection\ApiPlatformUtilsExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * API Platform Utils Bundle
 * Provides generic utilities for API Platform projects
 */
class ApiPlatformUtilsBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new ApiPlatformUtilsExtension();
        }
        return $this->extension;
    }
}
