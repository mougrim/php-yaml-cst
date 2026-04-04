<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Enum;

/** Tree-sitter node type identifiers for the YAML grammar. */
enum YamlNodeType: string
{
    case BLOCK_MAPPING_PAIR = 'block_mapping_pair';
    case BLOCK_MAPPING = 'block_mapping';
    case FLOW_PAIR = 'flow_pair';
    case ERROR = 'ERROR';
    case STREAM = 'stream';
    case DOCUMENT = 'document';
    case BLOCK_NODE = 'block_node';
    case FLOW_NODE = 'flow_node';
    case BLOCK_SEQUENCE = 'block_sequence';
    case BLOCK_SEQUENCE_ITEM = 'block_sequence_item';
    case FLOW_MAPPING = 'flow_mapping';
    case FLOW_SEQUENCE = 'flow_sequence';
    case PLAIN_SCALAR = 'plain_scalar';
    case SINGLE_QUOTE_SCALAR = 'single_quote_scalar';
    case DOUBLE_QUOTE_SCALAR = 'double_quote_scalar';
    case BLOCK_SCALAR = 'block_scalar';
    case ANCHOR = 'anchor';
    case ALIAS = 'alias';
    case TAG = 'tag';
    case COMMENT = 'comment';
}
