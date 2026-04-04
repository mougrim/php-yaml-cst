<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Factory;

use Mougrim\YamlCst\DomainModel\YamlCstNodeRef;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Exception\YamlSyntaxException;

/**
 * @internal Creates a {@see YamlSyntaxException} from a parse-error tree. Not part of the public API.
 */
readonly class YamlSyntaxExceptionFactory
{
    public function __construct(
        private YamlLineMapFactory $lineMapFactory,
    ) {
    }

    public function createFromTree(string $source, YamlCstNodeRef $root): YamlSyntaxException
    {
        $firstError = $this->findFirstError($root);

        if ($firstError !== null) {
            $startByte = $firstError->startByte();
            $line = $this->lineMapFactory->create($source)->locate($startByte)->line;
            $type = $firstError->type();
            $detail = ($type === null || $type === YamlNodeType::ERROR)
                ? 'unexpected content'
                : "unexpected node type '{$type->value}'";

            return new YamlSyntaxException(
                "YAML syntax error at line {$line} (byte {$startByte}): {$detail}",
            );
        }

        return new YamlSyntaxException('YAML syntax error detected in the document');
    }

    private function findFirstError(YamlCstNodeRef $node): ?YamlCstNodeRef
    {
        if ($node->isNull()) {
            return null;
        }

        if ($node->is(YamlNodeType::ERROR)) {
            return $node;
        }

        foreach ($node->namedChildren() as $child) {
            if ($child->hasError()) {
                $result = $this->findFirstError($child);

                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
