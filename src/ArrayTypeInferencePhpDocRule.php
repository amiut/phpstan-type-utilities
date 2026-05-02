<?php

declare(strict_types=1);

namespace Amiut\PHPStan\TypeUtilities;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use function array_map;
use function array_merge;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strpos;
use function substr;
use function strtolower;
use function trim;

/**
 * @implements Rule<FileNode>
 */
final class ArrayTypeInferencePhpDocRule implements Rule
{
    /**
     * @var ArrayReturnTypeInferer
     */
    private $inferer;

    public function __construct(ArrayReturnTypeInferer $inferer)
    {
        $this->inferer = $inferer;
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return $this->processStatements($node->getNodes(), null);
    }

    /**
     * @param Node[] $nodes
     * @return list<IdentifierRuleError>
     */
    private function processStatements(array $nodes, ?string $namespace): array
    {
        $errors = [];

        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $namespaceName = $node->name !== null ? $node->name->toString() : null;
                $errors = array_merge($errors, $this->processStatements($node->stmts, $namespaceName));
                continue;
            }

            if ($node instanceof Function_) {
                $functionName = $this->qualifyName($node->name->name, $namespace);
                $errors = array_merge($errors, $this->validateInferDoc($node, $functionName, null));
                $errors = array_merge($errors, $this->validateReturnTypeDocs($node, $namespace, null));
                continue;
            }

            if ($node instanceof Class_) {
                $className = $this->className($node, $namespace);

                if ($className === null) {
                    continue;
                }

                $errors = array_merge($errors, $this->validateReturnTypeDocs($node, $namespace, $className));

                foreach ($node->stmts as $member) {
                    if (!$member instanceof ClassMethod) {
                        continue;
                    }

                    $errors = array_merge($errors, $this->validateInferDoc($member, $member->name->name, $className));
                    $errors = array_merge($errors, $this->validateReturnTypeDocs($member, $namespace, $className));
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateInferDoc(Node $node, string $name, ?string $className): array
    {
        $docText = $this->docText($node);

        if ($docText === '' || preg_match('/@(?:phpstan-return|psalm-return|return)\s+array\b[^\r\n*]*@phpstan-infer-return\b/', $docText) !== 1) {
            return [];
        }

        $result = $className !== null
            ? $this->inferer->inferMethod($className, $name, true)
            : $this->inferer->inferFunction($name, true);

        if ($result->getType() !== null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '%s return type is marked @phpstan-infer-return, but the static array type could not be inferred: %s',
                $className !== null ? sprintf('Method %s::%s()', ltrim($className, '\\'), $name) : sprintf('Function %s()', $name),
                $result->getFailureReason() ?? 'unknown failure'
            ))
                ->identifier('arrayTypeInference.missingType')
                ->line($node->getLine())
                ->build(),
        ];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateReturnTypeDocs(Node $node, ?string $namespace, ?string $className): array
    {
        $docText = $this->docText($node);

        if ($docText === '') {
            return [];
        }

        if (preg_match_all('/ReturnType\s*<([^>]+)>/', $docText, $matches) === 0) {
            return [];
        }

        $errors = [];

        foreach ($matches[1] as $rawArgs) {
            $args = array_map('trim', explode(',', $rawArgs));

            if (count($args) === 1) {
                $functionName = $this->qualifyName($this->unquote($args[0]), $namespace);
                $result = $this->inferer->inferFunction($functionName, false);
            } elseif (count($args) === 2) {
                $targetClass = $this->resolveClassName($this->unquote($args[0]), $namespace, $className);
                $methodName = $this->unquote($args[1]);

                if ($targetClass === null) {
                    $result = ArrayReturnTypeInferenceResult::failure(sprintf('Class %s could not be resolved.', $args[0]));
                } else {
                    $result = $this->inferer->inferMethod($targetClass, $methodName, false);
                }
            } else {
                $result = ArrayReturnTypeInferenceResult::failure('ReturnType expects one function argument or two method arguments.');
            }

            if ($result->getType() !== null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'ReturnType<%s> could not be resolved: %s',
                $rawArgs,
                $result->getFailureReason() ?? 'unknown failure'
            ))
                ->identifier('arrayTypeInference.returnTypeUnresolved')
                ->line($node->getLine())
                ->build();
        }

        return $errors;
    }

    private function docText(Node $node): string
    {
        $doc = $node->getDocComment();

        return $doc !== null ? $doc->getText() : '';
    }

    private function className(Class_ $class, ?string $namespace): ?string
    {
        if ($class->name === null) {
            return null;
        }

        return $this->qualifyName($class->name->name, $namespace);
    }

    private function resolveClassName(string $className, ?string $namespace, ?string $currentClass): ?string
    {
        if (in_array(strtolower($className), ['self', 'static', '$this'], true)) {
            return $currentClass;
        }

        return $this->qualifyName($className, $namespace);
    }

    private function qualifyName(string $name, ?string $namespace): string
    {
        $name = trim($name);

        if ($name !== '' && $name[0] === '\\') {
            return '\\' . ltrim($name, '\\');
        }

        if (strpos($name, '\\') !== false) {
            return '\\' . ltrim($name, '\\');
        }

        if ($namespace === null || $namespace === '') {
            return '\\' . ltrim($name, '\\');
        }

        return '\\' . $namespace . '\\' . ltrim($name, '\\');
    }

    private function unquote(string $value): string
    {
        $value = trim($value);

        if (($value[0] === '\'' && substr($value, -1) === '\'')
            || ($value[0] === '"' && substr($value, -1) === '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
