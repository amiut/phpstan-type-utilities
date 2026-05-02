<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests;

use PHPUnit\Framework\TestCase;
use function array_map;
use function escapeshellarg;
use function exec;
use function implode;

final class ConsumerStylePhpStanTest extends TestCase
{
    public function testConsumerStyleReturnTypeResolutionAndAssignments(): void
    {
        $command = implode(' ', [
            escapeshellarg(__DIR__ . '/../vendor/bin/phpstan'),
            'analyse',
            '--configuration=' . escapeshellarg(__DIR__ . '/consumer-style.neon'),
            '--error-format=raw',
            '--no-progress',
            '--debug',
            '--memory-limit=2G',
            '2>&1',
        ]);

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $text = implode("\n", array_map('strval', $output));

        self::assertSame(1, $exitCode, $text);
        self::assertStringContainsString('Dumped type: array{schema_version: int, groups: array{}}', $text);
        self::assertStringContainsString("Dumped type: array{type: string, additionalProperties: bool", $text);
        self::assertStringContainsString("properties: array{schema_version: array{type: string, minimum: int, maximum: int}", $text);
        self::assertStringContainsString("Assigned value type 'a' does not match inferred array shape offset type int.", $text);
        self::assertStringNotContainsString('Assigned value type false does not match inferred array shape offset type', $text);
    }
}
