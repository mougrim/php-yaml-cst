<?php

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Mougrim\YamlCst;

use FFI;
use FFI\CData;
use Mougrim\YamlCst\Dto\YamlTreeSitterNodeHandle;
use Mougrim\YamlCst\Dto\YamlTreeSitterTreeHandle;
use Mougrim\YamlCst\Enum\YamlNodeField;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\Exception\AbiMismatchException;
use Mougrim\YamlCst\Exception\YamlTreeSitterException;
use Mougrim\YamlCst\Factory\YamlTreeSitterCoreFactory;

use function is_string;
use function strlen;

use const PHP_INT_MAX;

readonly class YamlTreeSitterCore
{
    /**
     * @internal Intended to be created via {@see YamlTreeSitterCoreFactory}.
     *           Constructing this directly with incorrect FFI instances will produce undefined behaviour.
     */
    public function __construct(
        private FFI $coreFfi,
        private FFI $yamlFfi,
    ) {
    }

    /**
     * Creates a new TSParser, configures it with the YAML grammar, parses $source, and returns
     * the resulting tree handle. Wraps ts_parser_new(), ts_parser_set_language(),
     * ts_parser_parse_string(), and ts_parser_delete().
     *
     * @throws AbiMismatchException    if the YAML grammar ABI is incompatible with the core library
     * @throws YamlTreeSitterException if any C function returns NULL
     */
    public function parseString(string $source): YamlTreeSitterTreeHandle
    {
        /** @var CData $parser */
        $parser = $this->coreFfi->ts_parser_new();

        if (FFI::isNull($parser)) {
            // @codeCoverageIgnoreStart
            throw new YamlTreeSitterException('ts_parser_new() returned NULL');
            // @codeCoverageIgnoreEnd
        }

        try {
            $language = $this->yamlLanguage();

            /** @var bool $success */
            $success = $this->coreFfi->ts_parser_set_language($parser, $language);

            if (!$success) {
                // @codeCoverageIgnoreStart
                throw new AbiMismatchException(
                    'Failed to set tree-sitter-yaml as the parser language. '
                    . 'This usually means an ABI mismatch between libtree-sitter and tree-sitter-yaml.'
                );
                // @codeCoverageIgnoreEnd
            }

            $nullTree = $this->coreFfi->cast('const TSTree *', 0);

            $sourceLength = strlen($source);

            if ($sourceLength > PHP_INT_MAX) {
                // @codeCoverageIgnoreStart
                throw new YamlTreeSitterException('Source string exceeds maximum supported length.');
                // @codeCoverageIgnoreEnd
            }

            /** @var CData $treePointer */
            $treePointer = $this->coreFfi->ts_parser_parse_string(
                $parser,
                $nullTree,
                $source,
                $sourceLength,
            );

            if (FFI::isNull($treePointer)) {
                // @codeCoverageIgnoreStart
                throw new YamlTreeSitterException('ts_parser_parse_string() returned NULL');
                // @codeCoverageIgnoreEnd
            }

            return new YamlTreeSitterTreeHandle($treePointer);
        } finally {
            $this->coreFfi->ts_parser_delete($parser);
        }
    }

    /** Wraps ts_tree_delete(). Frees the native tree; must be called exactly once per tree. */
    public function deleteTree(YamlTreeSitterTreeHandle $treeHandle): void
    {
        $this->coreFfi->ts_tree_delete($treeHandle->pointer);
    }

    /** Wraps ts_tree_root_node(). Returns the root node of the parsed tree. */
    public function treeRootNode(YamlTreeSitterTreeHandle $treeHandle): YamlTreeSitterNodeHandle
    {
        /** @var CData $nodeData */
        $nodeData = $this->coreFfi->ts_tree_root_node($treeHandle->pointer);

        return new YamlTreeSitterNodeHandle($nodeData);
    }

    /** Wraps ts_node_is_null(). Returns true for sentinel null nodes returned by tree-sitter. */
    public function nodeIsNull(YamlTreeSitterNodeHandle $nodeHandle): bool
    {
        return (bool) $this->coreFfi->ts_node_is_null($nodeHandle->data);
    }

    /**
     * Wraps ts_node_type(). Returns the grammar type of the node, or null for unknown types.
     */
    public function nodeType(YamlTreeSitterNodeHandle $nodeHandle): ?YamlNodeType
    {
        // ts_node_type() returns a C string (const char *). PHP FFI may expose it as a native
        // PHP string (when the bridge can convert automatically) or as a CData pointer (on some
        // PHP/FFI versions). Both cases are handled here for robustness.
        /** @var CData|string $result */
        $result = $this->coreFfi->ts_node_type($nodeHandle->data);
        $typeString = is_string($result) ? $result : FFI::string($result);

        return YamlNodeType::tryFrom($typeString);
    }

    /** Wraps ts_node_start_byte(). Returns the byte offset of the first byte of the node. */
    public function nodeStartByte(YamlTreeSitterNodeHandle $nodeHandle): int
    {
        return (int) $this->coreFfi->ts_node_start_byte($nodeHandle->data);
    }

    /** Wraps ts_node_end_byte(). Returns the byte offset one past the last byte of the node. */
    public function nodeEndByte(YamlTreeSitterNodeHandle $nodeHandle): int
    {
        return (int) $this->coreFfi->ts_node_end_byte($nodeHandle->data);
    }

    /**
     * Wraps ts_node_child_by_field_name(). Returns the child node for the given field,
     * or a null node if the field is absent.
     */
    public function nodeChildByFieldName(YamlTreeSitterNodeHandle $nodeHandle, YamlNodeField $field): YamlTreeSitterNodeHandle
    {
        /** @var CData $nodeData */
        $nodeData = $this->coreFfi->ts_node_child_by_field_name($nodeHandle->data, $field->value, strlen($field->value));

        return new YamlTreeSitterNodeHandle($nodeData);
    }

    /** Wraps ts_node_named_child_count(). Returns the number of named children of the node. */
    public function nodeNamedChildCount(YamlTreeSitterNodeHandle $nodeHandle): int
    {
        return (int) $this->coreFfi->ts_node_named_child_count($nodeHandle->data);
    }

    /** Wraps ts_node_named_child(). Returns the named child at the given zero-based index. */
    public function nodeNamedChild(YamlTreeSitterNodeHandle $nodeHandle, int $index): YamlTreeSitterNodeHandle
    {
        /** @var CData $nodeData */
        $nodeData = $this->coreFfi->ts_node_named_child($nodeHandle->data, $index);

        return new YamlTreeSitterNodeHandle($nodeData);
    }

    /** Wraps ts_node_has_error(). Returns true if the node or any of its descendants has an ERROR node. */
    public function nodeHasError(YamlTreeSitterNodeHandle $nodeHandle): bool
    {
        return (bool) $this->coreFfi->ts_node_has_error($nodeHandle->data);
    }

    /**
     * Wraps ts_node_is_extra(). Returns true for nodes that are not required by the grammar
     * (e.g. comments).
     */
    public function nodeIsExtra(YamlTreeSitterNodeHandle $nodeHandle): bool
    {
        return (bool) $this->coreFfi->ts_node_is_extra($nodeHandle->data);
    }

    /**
     * Wraps ts_node_next_named_sibling(). Returns the next named sibling, or a null node if none.
     */
    public function nodeNextNamedSibling(YamlTreeSitterNodeHandle $nodeHandle): YamlTreeSitterNodeHandle
    {
        /** @var CData $nodeData */
        $nodeData = $this->coreFfi->ts_node_next_named_sibling($nodeHandle->data);

        return new YamlTreeSitterNodeHandle($nodeData);
    }

    /**
     * Wraps ts_node_prev_named_sibling(). Returns the previous named sibling, or a null node if none.
     */
    public function nodePrevNamedSibling(YamlTreeSitterNodeHandle $nodeHandle): YamlTreeSitterNodeHandle
    {
        /** @var CData $nodeData */
        $nodeData = $this->coreFfi->ts_node_prev_named_sibling($nodeHandle->data);

        return new YamlTreeSitterNodeHandle($nodeData);
    }

    /**
     * Returns const TSLanguage * (already cast in the core scope).
     */
    private function yamlLanguage(): CData
    {
        /** @var CData $language */
        $language = $this->yamlFfi->tree_sitter_yaml();

        // cast between FFI scopes
        return $this->coreFfi->cast('const TSLanguage *', $language);
    }
}
