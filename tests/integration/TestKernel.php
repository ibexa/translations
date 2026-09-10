<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Translations;

use Ibexa\Bundle\Test\Core\IbexaTestCoreBundle;
use Ibexa\Bundle\Translations\IbexaTranslationsBundle;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use JMS\TranslationBundle\Translation\Extractor\FileExtractor;

final class TestKernel extends IbexaTestKernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();

        yield new IbexaTestCoreBundle();

        yield new IbexaTranslationsBundle();
    }

    protected static function getExposedServicesById(): iterable
    {
        yield from parent::getExposedServicesById();

        yield 'jms_translation.extractor.file_extractor' => FileExtractor::class;
    }
}
