<?php

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Mougrim\YamlCst\Factory;

use FFI;
use Mougrim\YamlCst\Exception\YamlTreeSitterException;
use Mougrim\YamlCst\YamlTreeSitterCore;

use function file_exists;
use function ini_get;

readonly class YamlTreeSitterCoreFactory
{
    private const string DEFAULT_CORE_LIB_PATH = '/usr/local/lib/libtree-sitter.so';
    private const string DEFAULT_YAML_LIB_PATH = '/usr/local/lib/libtree-sitter-yaml.so';

    public function create(
        string $coreLibPath = self::DEFAULT_CORE_LIB_PATH,
        string $yamlLibPath = self::DEFAULT_YAML_LIB_PATH,
    ): YamlTreeSitterCore {
        if (!file_exists($coreLibPath)) {
            throw new YamlTreeSitterException(
                "Tree-sitter core library not found: {$coreLibPath}. "
                . 'See the README for setup instructions: https://github.com/mougrim/php-yaml-cst#building-the-native-libraries',
            );
        }
        if (!file_exists($yamlLibPath)) {
            throw new YamlTreeSitterException(
                "Tree-sitter YAML grammar library not found: {$yamlLibPath}. "
                . 'See the README for setup instructions: https://github.com/mougrim/php-yaml-cst#building-the-native-libraries',
            );
        }

        // Due to it's hard to test
        // @codeCoverageIgnoreStart
        $ffiEnable = ini_get('ffi.enable');
        if ($ffiEnable !== '1' && $ffiEnable !== 'preload') {
            throw new YamlTreeSitterException(
                "FFI is disabled (ffi.enable={$ffiEnable}). Set ffi.enable=true in php.ini.",
            );
        }
        // @codeCoverageIgnoreEnd

        $coreFfi = FFI::cdef($this->coreHeader(), $coreLibPath);
        $yamlFfi = FFI::cdef($this->yamlHeader(), $yamlLibPath);

        return new YamlTreeSitterCore($coreFfi, $yamlFfi);
    }

    private function coreHeader(): string
    {
        // Minimal subset from tree_sitter/api.h
        return <<<'CDEF'
            #include <stdint.h>
            #include <stdbool.h>

            typedef struct TSParser TSParser;
            typedef struct TSTree TSTree;
            typedef struct TSLanguage TSLanguage;

            typedef struct TSPoint {
              uint32_t row;
              uint32_t column;
            } TSPoint;

            typedef struct TSNode {
              uint32_t context[4];
              const void *id;
              const TSTree *tree;
            } TSNode;

            TSParser *ts_parser_new(void);
            void ts_parser_delete(TSParser *self);

            bool ts_parser_set_language(TSParser *self, const TSLanguage *language);

            TSTree *ts_parser_parse_string(
              TSParser *self,
              const TSTree *old_tree,
              const char *string,
              uint32_t length
            );

            void ts_tree_delete(TSTree *self);
            TSNode ts_tree_root_node(const TSTree *self);

            const char *ts_node_type(TSNode self);
            uint32_t ts_node_start_byte(TSNode self);
            uint32_t ts_node_end_byte(TSNode self);

            bool ts_node_is_null(TSNode self);

            uint32_t ts_node_named_child_count(TSNode self);
            TSNode ts_node_named_child(TSNode self, uint32_t child_index);

            TSNode ts_node_child_by_field_name(
              TSNode self,
              const char *name,
              uint32_t name_length
            );

            bool ts_node_is_extra(TSNode self);
            bool ts_node_has_error(TSNode self);

            TSNode ts_node_next_named_sibling(TSNode self);
            TSNode ts_node_prev_named_sibling(TSNode self);
            CDEF;
    }

    private function yamlHeader(): string
    {
        // The symbol is usually named tree_sitter_yaml(), as in the tree-sitter docs examples
        return <<<'CDEF'
            typedef struct TSLanguage TSLanguage;
            const TSLanguage *tree_sitter_yaml(void);
            CDEF;
    }
}
