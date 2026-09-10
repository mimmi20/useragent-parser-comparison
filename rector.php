<?php

/**
 * This file is part of the mimmi20/useragent-parser-comparison package.
 *
 * Copyright (c) 2015-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\If_\RemoveDeadInstanceOfRector;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\YieldDataProviderRector;
use Rector\PHPUnit\CodeQuality\Rector\ClassMethod\NoSetupWithParentCallOverrideRector;
use Rector\PHPUnit\CodeQuality\Rector\FuncCall\AssertFuncCallToPHPUnitAssertRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/mappings',
        __DIR__ . '/src',
        __DIR__ . '/parsers/agent-zero/scripts',
        __DIR__ . '/parsers/browscap-php/scripts',
        __DIR__ . '/parsers/browser-detector/scripts',
        __DIR__ . '/parsers/cbschuld/scripts',
        __DIR__ . '/parsers/crawler-detect/scripts',
        __DIR__ . '/parsers/donatj/scripts',
        __DIR__ . '/parsers/endorphin/scripts',
        __DIR__ . '/parsers/foroco/scripts',
        __DIR__ . '/parsers/fyre-useragent/scripts',
        __DIR__ . '/parsers/jenssegers-agent/scripts',
        __DIR__ . '/parsers/matomo/scripts',
        __DIR__ . '/parsers/mobile-detect/scripts',
        __DIR__ . '/parsers/php-get-browser/scripts',
        __DIR__ . '/parsers/platine-php/scripts',
        __DIR__ . '/parsers/ua-parser-php/scripts',
        __DIR__ . '/parsers/whichbrowser-php/scripts',
        __DIR__ . '/parsers/wolfcast/scripts',
        __DIR__ . '/parsers/woothee-php/scripts',
        __DIR__ . '/tests/browscap/scripts',
        __DIR__ . '/tests/browser-detector/scripts',
        __DIR__ . '/tests/cbschuld/scripts',
        __DIR__ . '/tests/crawler-detect/scripts',
        __DIR__ . '/tests/curated/scripts',
        __DIR__ . '/tests/donatj/scripts',
        __DIR__ . '/tests/endorphin/scripts',
        __DIR__ . '/tests/matomo/scripts',
        __DIR__ . '/tests/mobile-detect/scripts',
        __DIR__ . '/tests/ua-parser-js/scripts',
        __DIR__ . '/tests/ua-parser-php/scripts',
        __DIR__ . '/tests/whichbrowser-php/scripts',
        __DIR__ . '/tests/woothee-php/scripts',
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        naming: true,
        namedArgs: true,
        instanceOf: true,
        if: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
        phpunitMockToStub: true,
    )
    ->withPhpSets(php85: true)
    ->withAttributesSets(phpunit: true)
    ->withComposerBased(phpunit: true)
    ->withSkip([
        RemoveDeadInstanceOfRector::class,
        RemoveAlwaysTrueIfConditionRector::class,
        RemoveParentCallWithoutParentRector::class,
        NewMethodCallWithoutParenthesesRector::class,
        PreferPHPUnitThisCallRector::class,
        AssertFuncCallToPHPUnitAssertRector::class,
        YieldDataProviderRector::class,
        RenamePropertyToMatchTypeRector::class,
        RenameParamToMatchTypeRector::class,
        NoSetupWithParentCallOverrideRector::class,
    ])
    ->withoutParallel()
    ->withMemoryLimit('2048M');
