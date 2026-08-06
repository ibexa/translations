<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Translations\Extractor;

use Doctrine\Common\Annotations\DocParser;
use JMS\TranslationBundle\Annotation\Desc;
use JMS\TranslationBundle\Logger\LoggerAwareInterface;
use JMS\TranslationBundle\Model\FileSource;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;
use Peast\Peast;
use Peast\Syntax\Exception;
use Peast\Syntax\Node;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use SplFileInfo;
use Twig\Node\Node as TwigNode;

final class JavaScriptFileVisitor implements FileVisitorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const string TRANSLATOR_OBJECT = 'Translator';
    public const string TRANSLATOR_TRANS_METHOD = 'trans';
    public const string TRANSLATOR_TRANS_CHOICE_METHOD = 'transChoice';

    public const int ID_ARG = 0;
    public const int TRANS_DOMAIN_ARG = 2;
    public const int TRANS_CHOICE_DOMAIN_ARG = 3;

    private DocParser $docParser;

    public function __construct(private readonly string $defaultDomain = 'messages')
    {
        $this->logger = new NullLogger();

        $this->docParser = new DocParser();
        $this->docParser->setIgnoreNotImportedAnnotations(true);
        $this->docParser->setImports([
            'desc' => Desc::class,
        ]);
    }

    public function visitFile(
        SplFileInfo $file,
        MessageCatalogue $catalogue
    ): void {
        if (!$this->supports($file)) {
            return;
        }

        $ast = $this->parseFile($file);
        if ($ast === null) {
            return;
        }

        $ast->traverse(function (Node\Node $node) use ($catalogue, $file): void {
            $this->visitNode($node, $file, $catalogue);
        });
    }

    private function parseFile(SplFileInfo $file): ?Node\Program
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return null;
        }

        $source = file_get_contents($realPath);
        if ($source === false) {
            $this->logger?->error(sprintf('Unable to read file %s.', $realPath));

            return null;
        }

        try {
            $parser = Peast::latest($source, [
                'comments' => true,
                'jsx' => true,
                'sourceType' => Peast::SOURCE_TYPE_MODULE,
            ]);

            return $parser->parse();
        } catch (Exception $e) {
            $this->logger?->error(sprintf(
                'Unable to parse file %s: %s in line %d column %d',
                $realPath,
                $e->getMessage(),
                $e->getPosition()->getLine(),
                $e->getPosition()->getColumn()
            ));

            return null;
        }
    }

    private function visitNode(
        Node\Node $node,
        SplFileInfo $file,
        MessageCatalogue $catalogue
    ): void {
        if (!$node instanceof Node\CallExpression) {
            return;
        }

        $methodName = $this->getTranslatorMethodName($node);
        if ($methodName === null) {
            return;
        }

        $arguments = $node->getArguments();
        $id = $this->extractId($file, $arguments);
        if ($id === null) {
            return;
        }

        $message = new Message(
            $id,
            $this->extractDomain($file, $arguments, $methodName) ?? $this->defaultDomain
        );

        $description = $this->extractDesc($arguments);
        if ($description !== null) {
            $message->setDesc($description);
        }

        $message->addSource(new FileSource((string)$file));
        $catalogue->add($message);
    }

    /**
     * Returns the name of the called translator method, or null if the call is not a translator call.
     */
    private function getTranslatorMethodName(Node\CallExpression $node): ?string
    {
        $callee = $node->getCallee();
        if (!$callee instanceof Node\MemberExpression) {
            return null;
        }

        $object = $callee->getObject();
        $property = $callee->getProperty();
        if (!$object instanceof Node\Identifier || !$property instanceof Node\Identifier) {
            return null;
        }

        if ($object->getName() !== self::TRANSLATOR_OBJECT) {
            return null;
        }

        $methodName = $property->getName();
        if (!in_array($methodName, [self::TRANSLATOR_TRANS_METHOD, self::TRANSLATOR_TRANS_CHOICE_METHOD], true)) {
            return null;
        }

        return $methodName;
    }

    /**
     * @param array<mixed> $ast
     */
    public function visitPhpFile(
        SplFileInfo $file,
        MessageCatalogue $catalogue,
        array $ast
    ): void {}

    public function visitTwigFile(
        SplFileInfo $file,
        MessageCatalogue $catalogue,
        TwigNode $ast
    ): void {}

    /**
     * Extracts a message domain from the translator call.
     *
     * @param array<Node\Expression|Node\SpreadElement> $arguments
     */
    private function extractId(
        SplFileInfo $file,
        array $arguments
    ): ?string {
        $idNode = $arguments[self::ID_ARG] ?? null;
        if (!$idNode instanceof Node\StringLiteral) {
            if ($idNode instanceof Node\Node) {
                $this->logNonLiteralArgument('id', $file, $idNode);
            }

            return null;
        }

        $id = $idNode->getValue();

        return is_string($id) ? $id : null;
    }

    /**
     * Extracts a message domain from the translator call.
     *
     * @param array<Node\Expression|Node\SpreadElement> $arguments
     */
    private function extractDomain(
        SplFileInfo $file,
        array $arguments,
        string $methodName
    ): ?string {
        $domainArgIndex = $methodName === self::TRANSLATOR_TRANS_METHOD
            ? self::TRANS_DOMAIN_ARG
            : self::TRANS_CHOICE_DOMAIN_ARG;

        $domainNode = $arguments[$domainArgIndex] ?? null;
        if ($domainNode === null) {
            return null;
        }

        if (!$domainNode instanceof Node\StringLiteral) {
            if ($domainNode instanceof Node\Node) {
                $this->logNonLiteralArgument('domain', $file, $domainNode);
            }

            return null;
        }

        $domain = $domainNode->getValue();

        return is_string($domain) ? $domain : null;
    }

    /**
     * Extracts a message description from the translator call.
     *
     * @param array<Node\Expression|Node\SpreadElement> $arguments
     */
    private function extractDesc(array $arguments): ?string
    {
        $idNode = $arguments[self::ID_ARG] ?? null;
        if (!$idNode instanceof Node\Node) {
            return null;
        }

        foreach ($idNode->getLeadingComments() as $comment) {
            $annotations = $this->docParser->parse($comment->getText());
            foreach ($annotations as $annotation) {
                if ($annotation instanceof Desc) {
                    return $annotation->text;
                }
            }
        }

        return null;
    }

    private function logNonLiteralArgument(
        string $argumentName,
        SplFileInfo $file,
        Node\Node $node
    ): void {
        $position = $node->getLocation()->getStart();

        $this->logger?->error(sprintf(
            'Could not extract %s, expected string literal but got %s (in %s on line %d column %d).',
            $argumentName,
            $node->getType(),
            $file->getRealPath(),
            $position->getLine(),
            $position->getColumn()
        ));
    }

    private function supports(SplFileInfo $file): bool
    {
        $realPath = $file->getRealPath();

        return $realPath !== false && str_ends_with($realPath, '.js') && !str_ends_with($realPath, '.min.js');
    }
}
