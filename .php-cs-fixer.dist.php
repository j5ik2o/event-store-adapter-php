<?php
/*
 * This document has been generated with
 * https://mlocati.github.io/php-cs-fixer-configurator/#version:3.12.0|configurator
 * you can change this configuration by importing this file.
 */
$config = new \PhpCsFixer\Config();
return $config
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        'no_unused_imports' => true,
        'braces_position' => [
            'classes_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
            'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end'
        ],
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->exclude('vendor')
        ->in(__DIR__)
    );