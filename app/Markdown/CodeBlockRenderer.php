<?php

declare(strict_types=1);

namespace App\Markdown;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/** @internal */
final class CodeBlockRenderer implements NodeRendererInterface
{
    public function __construct(private readonly Highlighter $highlighter) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if ($node instanceof FencedCode) {
            return $this->highlighter->block(rtrim($node->getLiteral(), "\n"), $node->getInfoWords()[0] ?? '');
        }
        if ($node instanceof IndentedCode) {
            return $this->highlighter->block(rtrim($node->getLiteral(), "\n"));
        }

        throw new InvalidArgumentException('Only code blocks can be rendered here, not ' . $node::class . '.');
    }
}
