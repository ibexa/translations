<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Translations\Extractor;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use JMS\TranslationBundle\Translation\Extractor\FileExtractor;

final class TranslationExtractorTest extends IbexaKernelTestCase
{
    public function testExtractsJavaScriptAndTypeScriptMessages(): void
    {
        self::bootKernel();

        $fileExtractor = self::getContainer()->get(IbexaTestKernel::getAliasServiceId(
            'jms_translation.extractor.file_extractor',
        ));
        self::assertInstanceOf(FileExtractor::class, $fileExtractor);

        $fileExtractor->setDirectory(__DIR__ . '/../Resources/translation_extractors');
        $fileExtractor->setPattern(['*.js', '*.ts']);

        $catalogue = $fileExtractor->extract();

        self::assertSame(
            'JavaScript fixture description',
            $catalogue->get('integration.javascript', 'ibexa_integration')->getDesc(),
        );
        self::assertSame(
            'TypeScript fixture description',
            $catalogue->get('integration.typescript', 'ibexa_integration')->getDesc(),
        );
    }
}
