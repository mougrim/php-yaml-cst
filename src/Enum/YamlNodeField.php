<?php

declare(strict_types=1);

namespace Mougrim\YamlCst\Enum;

/** Tree-sitter field names used to navigate YAML CST nodes. */
enum YamlNodeField: string
{
    case KEY = 'key';
    case VALUE = 'value';
}
