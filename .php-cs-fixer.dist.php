<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return new Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@auto' => true,
        '@PhpCsFixer' => true,
        'increment_style' => ['style' => 'post'],
        'single_line_empty_body' => false,
        'phpdoc_align' => false,
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'phpdoc_to_comment' => false,
        'yoda_style' => false,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'php_unit_data_provider_method_order' => ['placement' => 'before'],
        'php_unit_internal_class' => false,
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
    ])
    ->setFinder(
        new Finder()
            ->in(__DIR__),
    )
;
