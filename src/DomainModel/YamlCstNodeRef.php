<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\DomainModel;

use Mougrim\YamlCst\Dto\YamlTreeSitterNodeHandle;
use Mougrim\YamlCst\Enum\YamlNodeField;
use Mougrim\YamlCst\Enum\YamlNodeType;
use Mougrim\YamlCst\YamlTreeSitterCore;

use function substr;

/**
 * A reference to a single node in the tree-sitter Concrete Syntax Tree.
 *
 * Not declared `readonly` because cached FFI results are written lazily after construction.
 * From a caller's perspective this class is fully immutable: the cached properties are pure
 * memoisation of deterministic FFI calls and are never exposed directly.
 *
 * Two sentinel conventions are used, forced by PHP's type system:
 * - `false` for fields whose valid computed value can be `null` (i.e. `$cachedType`: unknown type)
 *   or `int` (byte offsets where 0 is valid, making `null` unusable as sentinel).
 * - `null` for `bool` fields, because `false|bool` is not a legal PHP union (bool includes false).
 */
class YamlCstNodeRef
{
    // Cached FFI call results. Sentinels: `false` = not yet computed for type/int fields;
    // `null` = not yet computed for bool fields (false|bool is not a valid PHP union type).
    private false|YamlNodeType|null $cachedType = false;
    private ?bool $cachedIsNull = null;
    private false|int $cachedStartByte = false;
    private false|int $cachedEndByte = false;
    private ?bool $cachedHasError = null;
    private ?bool $cachedIsExtra = null;

    /**
     * @param YamlCstTree $treeOwner Holds a strong reference to the tree that owns this node. Tree-sitter nodes are
     * structs that embed a raw pointer back to their TSTree. If the TSTree were freed
     * (via YamlCstTree::__destruct → ts_tree_delete) while a node is still in use, any access to the node would be
     * a use-after-free. Keeping $treeOwner alive here ensures the tree's lifetime is at least as long as this node
     * reference.
     */
    public function __construct(
        private readonly YamlTreeSitterCore $core,
        private readonly YamlCstTree $treeOwner,
        private readonly YamlTreeSitterNodeHandle $nodeHandle,
    ) {
    }

    public function isNull(): bool
    {
        if ($this->cachedIsNull === null) {
            $this->cachedIsNull = $this->core->nodeIsNull($this->nodeHandle);
        }

        return $this->cachedIsNull;
    }

    public function type(): ?YamlNodeType
    {
        if ($this->cachedType === false) {
            $this->cachedType = $this->core->nodeType($this->nodeHandle);
        }

        return $this->cachedType;
    }

    public function is(YamlNodeType $type): bool
    {
        return $this->type() === $type;
    }

    public function startByte(): int
    {
        if ($this->cachedStartByte === false) {
            $this->cachedStartByte = $this->core->nodeStartByte($this->nodeHandle);
        }

        return $this->cachedStartByte;
    }

    public function endByte(): int
    {
        if ($this->cachedEndByte === false) {
            $this->cachedEndByte = $this->core->nodeEndByte($this->nodeHandle);
        }

        return $this->cachedEndByte;
    }

    public function span(): YamlSpan
    {
        return new YamlSpan($this->startByte(), $this->endByte());
    }

    public function childByField(YamlNodeField $field): self
    {
        $childHandle = $this->core->nodeChildByFieldName($this->nodeHandle, $field);

        return new self($this->core, $this->treeOwner, $childHandle);
    }

    /**
     * @return list<YamlCstNodeRef>
     */
    public function namedChildren(): array
    {
        $count = $this->core->nodeNamedChildCount($this->nodeHandle);
        $children = [];

        for ($i = 0; $i < $count; $i++) {
            $children[] = new self($this->core, $this->treeOwner, $this->core->nodeNamedChild($this->nodeHandle, $i));
        }

        return $children;
    }

    public function hasError(): bool
    {
        if ($this->cachedHasError === null) {
            $this->cachedHasError = $this->core->nodeHasError($this->nodeHandle);
        }

        return $this->cachedHasError;
    }

    /**
     * Comments are extras.
     */
    public function isExtra(): bool
    {
        if ($this->cachedIsExtra === null) {
            $this->cachedIsExtra = $this->core->nodeIsExtra($this->nodeHandle);
        }

        return $this->cachedIsExtra;
    }

    /**
     * Returns the next named sibling of this node, or a null node if there is none.
     *
     * Use {@see isNull()} on the result to check whether a sibling exists.
     */
    public function nextNamedSibling(): self
    {
        $siblingHandle = $this->core->nodeNextNamedSibling($this->nodeHandle);

        return new self($this->core, $this->treeOwner, $siblingHandle);
    }

    /**
     * Returns the previous named sibling of this node, or a null node if there is none.
     *
     * Use {@see isNull()} on the result to check whether a sibling exists.
     */
    public function previousNamedSibling(): self
    {
        $siblingHandle = $this->core->nodePrevNamedSibling($this->nodeHandle);

        return new self($this->core, $this->treeOwner, $siblingHandle);
    }

    public function text(string $source): string
    {
        return substr($source, $this->startByte(), $this->endByte() - $this->startByte());
    }
}
