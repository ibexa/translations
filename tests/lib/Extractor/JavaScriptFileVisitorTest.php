<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Translations\Extractor;

use Ibexa\Translations\Extractor\JavaScriptFileVisitor;
use JMS\TranslationBundle\Model\MessageCatalogue;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SplFileInfo;

final class JavaScriptFileVisitorTest extends TestCase
{
    public function testVisitFileExtractsTranslatorMessages(): void
    {
        $filePath = $this->createJavaScriptFixture(<<<'JS'
            Translator.trans(
                /* @Desc("Save button label") */ 'fixture.save',
                {},
                'ibexa_fixture'
            );
            Translator.transChoice('fixture.items', count, {}, 'ibexa_fixture');
            JS);

        $catalogue = new MessageCatalogue();

        try {
            (new JavaScriptFileVisitor())->visitFile(new SplFileInfo($filePath), $catalogue);

            self::assertSame(
                'Save button label',
                $catalogue->get('fixture.save', 'ibexa_fixture')->getDesc(),
            );
            self::assertSame(
                'fixture.items',
                $catalogue->get('fixture.items', 'ibexa_fixture')->getId(),
            );
        } finally {
            unlink($filePath);
        }
    }

    public function testVisitFileLogsAndSkipsNonLiteralId(): void
    {
        $filePath = $this->createJavaScriptFixture(<<<'JS'
            const id = 'fixture.dynamic';
            Translator.trans(id, {}, 'ibexa_fixture');
            JS);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::stringContains('Could not extract id, expected string literal but got Identifier'));

        $visitor = new JavaScriptFileVisitor();
        $visitor->setLogger($logger);

        $catalogue = new MessageCatalogue();

        try {
            $visitor->visitFile(new SplFileInfo($filePath), $catalogue);

            self::assertSame([], $catalogue->getDomains());
        } finally {
            unlink($filePath);
        }
    }

    private function createJavaScriptFixture(string $source): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'js_extractor_');
        self::assertNotFalse($filePath);

        $javaScriptFilePath = $filePath . '.js';
        self::assertTrue(rename($filePath, $javaScriptFilePath));
        self::assertNotFalse(file_put_contents($javaScriptFilePath, $source));

        return $javaScriptFilePath;
    }
}
