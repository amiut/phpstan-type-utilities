<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities\Tests;

use Amiut\PHPStan\TypeUtilities\ArrayReturnTypeInferer;
use Amiut\PHPStan\TypeUtilities\Tests\Fixtures\InferFixture;
use Amiut\PHPStan\TypeUtilities\Tests\Fixtures\ReturnTypeUseCasesFixture;
use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;

/**
 * @extends PHPStanTestCase<\PHPStan\Rules\Rule>
 */
final class ReturnTypeNodeResolverExtensionTest extends PHPStanTestCase
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

    public function testReturnTypeMethodFallsBackToReflectedNativeReturnType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode(InferFixture::class),
                new IdentifierTypeNode("'count'"),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(IntegerType::class, $type);
    }

    public function testReturnTypeMethodFallsBackToReflectedObjectReturnType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode(InferFixture::class),
                new IdentifierTypeNode("'value'"),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(ObjectType::class, $type);
        self::assertSame('Amiut\PHPStan\TypeUtilities\Tests\Fixtures\InferValue', $type->getClassName());
    }

    public function testReturnTypeFunctionFallsBackToReflectedNativeReturnType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode('Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\sharedLimit'),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(IntegerType::class, $type);
    }

    public function testReturnTypeMethodUsesPhpStanResolvedPhpDocReturnType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode(InferFixture::class),
                new IdentifierTypeNode("'documentedShape'"),
            ]
        );

        $type = $resolver->resolve($typeNode, $nameScope);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $keys = array_map(
            static function ($k) { return $k->getValue(); },
            $type->getKeyTypes()
        );
        self::assertContains('name', $keys);
        self::assertContains('count', $keys);
    }

    public function testReturnTypeFunctionUsesPhpStanResolvedPhpDocReturnType(): void
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        $typeNode = new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            [
                new IdentifierTypeNode('Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\documentedOptions'),
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

    public function testInferReturnMethodInfersOwnStaticArrayShapeInIsolation(): void
    {
        $inferer = self::getContainer()->getByType(ArrayReturnTypeInferer::class);

        $result = $inferer->inferMethod(ReturnTypeUseCasesFixture::class, 'inferredOptions', false);
        $type = $result->getType();

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    public function testInferReturnFunctionInfersOwnStaticArrayShapeInIsolation(): void
    {
        class_exists(ReturnTypeUseCasesFixture::class);
        $inferer = self::getContainer()->getByType(ArrayReturnTypeInferer::class);

        $result = $inferer->inferFunction('\\Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\isolatedInferredOptions', false);
        $type = $result->getType();

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    public function testReturnTypeNativeMethodResolvesInIsolation(): void
    {
        $type = $this->resolveReturnType([
            new IdentifierTypeNode(ReturnTypeUseCasesFixture::class),
            new IdentifierTypeNode("'nativeCount'"),
        ]);

        self::assertInstanceOf(IntegerType::class, $type);
    }

    public function testReturnTypeNativeFunctionResolvesInIsolation(): void
    {
        class_exists(ReturnTypeUseCasesFixture::class);

        $type = $this->resolveReturnType([
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\isolatedNativeCount'),
        ]);

        self::assertInstanceOf(IntegerType::class, $type);
    }

    public function testReturnTypeObjectMethodResolvesInIsolation(): void
    {
        $type = $this->resolveReturnType([
            new IdentifierTypeNode(ReturnTypeUseCasesFixture::class),
            new IdentifierTypeNode("'nativeValue'"),
        ]);

        self::assertInstanceOf(ObjectType::class, $type);
        self::assertSame('Amiut\PHPStan\TypeUtilities\Tests\Fixtures\IsolatedValue', $type->getClassName());
    }

    public function testReturnTypePhpDocMethodResolvesInIsolation(): void
    {
        $type = $this->resolveReturnType([
            new IdentifierTypeNode(ReturnTypeUseCasesFixture::class),
            new IdentifierTypeNode("'documentedOptions'"),
        ]);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    public function testReturnTypePhpDocFunctionResolvesInIsolation(): void
    {
        class_exists(ReturnTypeUseCasesFixture::class);

        $type = $this->resolveReturnType([
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\isolatedDocumentedOptions'),
        ]);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    public function testReturnTypeInferredMethodResolvesInIsolation(): void
    {
        $type = $this->resolveReturnType([
            new IdentifierTypeNode(ReturnTypeUseCasesFixture::class),
            new IdentifierTypeNode("'inferredOptions'"),
        ]);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    public function testReturnTypeInferredFunctionResolvesInIsolation(): void
    {
        class_exists(ReturnTypeUseCasesFixture::class);

        $type = $this->resolveReturnType([
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\Tests\\Fixtures\\isolatedInferredOptions'),
        ]);

        self::assertInstanceOf(ConstantArrayType::class, $type);
        $this->assertConstantArrayKeys($type, ['enabled', 'limit', 'label']);
    }

    /**
     * @param list<IdentifierTypeNode> $genericTypes
     */
    private function resolveReturnType(array $genericTypes): \PHPStan\Type\Type
    {
        $resolver = self::getContainer()->getByType(TypeNodeResolver::class);
        $nameScope = new NameScope(null, []);

        return $resolver->resolve(new GenericTypeNode(
            new IdentifierTypeNode('\\Amiut\\PHPStan\\TypeUtilities\\ReturnType'),
            $genericTypes
        ), $nameScope);
    }

    /**
     * @param list<string> $expectedKeys
     */
    private function assertConstantArrayKeys(ConstantArrayType $type, array $expectedKeys): void
    {
        $keys = array_map(
            static function ($keyType) { return $keyType->getValue(); },
            $type->getKeyTypes()
        );

        foreach ($expectedKeys as $expectedKey) {
            self::assertContains($expectedKey, $keys);
        }
    }
}
