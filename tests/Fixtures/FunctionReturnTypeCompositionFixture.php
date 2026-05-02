<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests\Fixtures;

/**
 * @return array @phpstan-infer-return
 */
function inferredTransportConfig(): array
{
    return [
        'enabled' => true,
        'retryLimit' => 3,
        'endpoint' => 'https://example.test',
    ];
}

/**
 * @phpstan-type TransportConfig \ReturnType<Amiut\PHPStan\TypeUtilities\Tests\Fixtures\inferredTransportConfig>
 */
final class FunctionReturnTypeCompositionFixture
{
    /**
     * @param TransportConfig $config
     */
    public function validConfig(array $config): void
    {
        $config['enabled'] = true;
        $config['retryLimit'] = 3;
        $config['endpoint'] = 'https://example.test';
    }

    /**
     * @param TransportConfig $config
     */
    public function invalidEnabled(array $config): void
    {
        $config['enabled'] = 'yes';
    }

    /**
     * @param TransportConfig $config
     */
    public function invalidRetryLimit(array $config): void
    {
        $config['retryLimit'] = 'three';
    }

    /**
     * @param TransportConfig $config
     */
    public function invalidEndpoint(array $config): void
    {
        $config['endpoint'] = false;
    }
}
