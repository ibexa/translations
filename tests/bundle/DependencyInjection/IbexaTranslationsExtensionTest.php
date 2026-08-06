<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Translations\DependencyInjection;

use Ibexa\Bundle\Translations\DependencyInjection\IbexaTranslationsExtension;
use Ibexa\Translations\Extractor\JavaScriptFileVisitor;
use Ibexa\Translations\Extractor\TypeScriptExtractorClient;
use Ibexa\Translations\Extractor\TypeScriptFileVisitor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class IbexaTranslationsExtensionTest extends TestCase
{
    public function testLoadsTranslationExtractorServices(): void
    {
        $container = new ContainerBuilder();

        (new IbexaTranslationsExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(JavaScriptFileVisitor::class));
        self::assertTrue($container->getDefinition(JavaScriptFileVisitor::class)->hasTag('jms_translation.file_visitor'));

        self::assertTrue($container->hasDefinition(TypeScriptFileVisitor::class));
        self::assertTrue($container->getDefinition(TypeScriptFileVisitor::class)->hasTag('jms_translation.file_visitor'));

        self::assertTrue($container->hasDefinition(TypeScriptExtractorClient::class));
    }
}
