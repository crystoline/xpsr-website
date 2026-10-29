<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Docs\DocVersion;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Renders the repository's Markdown as GitHub does, for one documentation page at a time:
 * GitHub Flavored Markdown, heading IDs that match GitHub's, links rewritten for the site,
 * and highlighted code.
 */
final class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;
    private ?RenderContext $context = null;

    public function __construct(private readonly LinkResolver $links, Highlighter $highlighter)
    {
        $environment = new Environment([
            // The documentation is the project's own, so its HTML (such as comments) is kept.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'html_class' => 'heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'apply_id_to_heading' => true,
                'insert' => 'after',
                // The anchor shows "#" through CSS, so search excerpts and copied text leave it out.
                'symbol' => '',
                'title' => 'Link to this section',
            ],
            'slug_normalizer' => ['instance' => new GithubSlugNormalizer()],
            'table' => ['wrap' => ['enabled' => true, 'tag' => 'div', 'attributes' => ['class' => 'table-wrap']]],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new HeadingPermalinkExtension());
        $codeBlocks = new CodeBlockRenderer($highlighter);
        $environment->addRenderer(FencedCode::class, $codeBlocks, 10);
        $environment->addRenderer(IndentedCode::class, $codeBlocks, 10);
        $environment->addEventListener(DocumentParsedEvent::class, $this->rewriteLinks(...));
        // After HeadingPermalinkExtension (-100) has given every heading its ID.
        $environment->addEventListener(DocumentParsedEvent::class, $this->collectHeadings(...), -200);
        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @param string $path the repository file, such as docs/ROUTING.md; relative links start from it
     * @param array<string, string> $pages repository path => site URL of every page in this version
     */
    public function render(string $markdown, DocVersion $version, string $path, array $pages): RenderedMarkdown
    {
        $this->context = $context = new RenderContext($version, $path, $pages);
        try {
            $html = (string) $this->converter->convert($markdown);
        } finally {
            $this->context = null;
        }

        return new RenderedMarkdown($html, $context->title, $context->headings, array_values(array_unique($context->problems)));
    }

    private function rewriteLinks(DocumentParsedEvent $event): void
    {
        if ($this->context === null) {
            return;
        }
        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Link) {
                $node->setUrl($this->links->link($node->getUrl(), $this->context));
            } elseif ($node instanceof Image) {
                $node->setUrl($this->links->image($node->getUrl(), $this->context));
            }
        }
    }

    private function collectHeadings(DocumentParsedEvent $event): void
    {
        if ($this->context === null) {
            return;
        }
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (!$node instanceof Heading) {
                continue;
            }
            $text = trim(StringContainerHelper::getChildText($node, [RawMarkupContainerInterface::class]));
            if ($node->getLevel() === 1) {
                $this->context->title ??= $text;
                // The title needs no anchor, and search would list it as a section of its own
                // page. Its ID was still counted, so later IDs match GitHub's.
                $node->data->remove('attributes/id');
                foreach ([...$node->children()] as $child) {
                    if ($child instanceof HeadingPermalink) {
                        $child->detach();
                    }
                }
            } elseif ($node->getLevel() <= 3) {
                $id = $node->data->get('attributes/id', '');
                $this->context->headings[] = ['level' => $node->getLevel(), 'text' => $text, 'id' => is_string($id) ? $id : ''];
            }
        }
    }
}
