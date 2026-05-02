<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests;

use Amiut\PHPStan\TypeUtilities\Tests\Fixtures\InferFixture;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantArrayType;

/**
 * @extends PHPStanTestCase<\PHPStan\Rules\Rule>
 */
final class InferTypeNodeResolverExtensionTest extends PHPStanTestCase
{
    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../extension.neon'];
    }

    public function testReturnTypeMethodResolvesToConstantArrayType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('ReturnType'),
            [
                new IdentifierTypeNode(InferFixture::class),
                new IdentifierTypeNode("'options'"),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $keys = array_map(
            static function ($k) { return $k->getValue(); },
            $type->getKeyTypes()
        );
        self::assertContains('enabled', $keys);
        self::assertContains('limit', $keys);
    }

    public function testReturnTypeMethodNestedResolvesToConstantArrayType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode(InferFixture::class),
                new IdentifierTypeNode("'nested'"),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $keys = array_map(
            static function ($k) { return $k->getValue(); },
            $type->getKeyTypes()
        );
        self::assertContains('options', $keys);
        self::assertContains('version', $keys);

        $optionsValueType = $type->getValueTypes()[array_search('options', $keys, true)];
        self::assertInstanceOf(ConstantArrayType::class, $optionsValueType);
    }

    public function testReturnTypeFunctionResolvesToConstantArrayType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode('Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\sharedOptions'),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertNotNull($type);
        self::assertInstanceOf(ConstantArrayType::class, $type);
    }
}
