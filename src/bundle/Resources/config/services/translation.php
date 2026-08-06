<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\Translations\Extractor\JavaScriptFileVisitor;
use Ibexa\Translations\Extractor\TypeScriptExtractorClient;
use Ibexa\Translations\Extractor\TypeScriptFileVisitor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services
        ->set(JavaScriptFileVisitor::class)
        ->tag('jms_translation.file_visitor');

    $services
        ->set(TypeScriptFileVisitor::class)
        ->tag('jms_translation.file_visitor');

    $services->set(TypeScriptExtractorClient::class);
};
