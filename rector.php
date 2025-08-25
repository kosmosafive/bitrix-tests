<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
        ]
    )
    ->withCache(cacheDirectory: 'tmp/rector')
    ->withImportNames(
        importNames: false,
        importDocBlockNames: false,
        importShortClasses: false
    )
    ->withPhpSets(
        php83: true
    )
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true
    )
    ->withSkip(
        [
            RenamePropertyToMatchTypeRector::class,
        ]
    );
