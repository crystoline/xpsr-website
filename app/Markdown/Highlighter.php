<?php

declare(strict_types=1);

namespace App\Markdown;

use Phiki\Phiki;
use Phiki\Theme\Theme;

/**
 * Highlights code when the site is built, with Phiki (TextMate grammars, as in VS Code), so
 * pages need no highlighting script. Each token carries a light colour and, as a CSS
 * variable, a dark one.
 */
final class Highlighter
{
    /** Fence names without a grammar of their own. */
    private const GRAMMARS = ['latte' => 'html', 'console' => 'shellscript'];

    /** What the label on a code block says. */
    private const LABELS = [
        'php' => 'PHP', 'sh' => 'Shell', 'bash' => 'Shell', 'shell' => 'Shell', 'console' => 'Shell',
        'twig' => 'Twig', 'blade' => 'Blade', 'latte' => 'Latte', 'html' => 'HTML', 'nginx' => 'nginx',
        'apache' => 'Apache', 'ini' => 'INI', 'json' => 'JSON', 'js' => 'JavaScript', 'yaml' => 'YAML',
        'xml' => 'XML', 'sql' => 'SQL', 'diff' => 'Diff', 'dotenv' => '.env', 'cron' => 'cron',
        'mermaid' => 'Mermaid', 'dockerfile' => 'Dockerfile', 'css' => 'CSS',
    ];

    public function __construct(private readonly Phiki $phiki = new Phiki()) {}

    /** A code block: the highlighted code and a label naming its language. */
    public function block(string $code, string $language = ''): string
    {
        $language = strtolower(trim($language));
        $grammar = self::GRAMMARS[$language] ?? $language;
        if ($grammar === '' || in_array($grammar, ['text', 'txt', 'plain'], true) || !$this->phiki->environment->grammars->has($grammar)) {
            $pre = '<pre class="phiki plain"><code>' . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE) . '</code></pre>';
        } else {
            $pre = $this->phiki->codeToHtml($code, $grammar, ['light' => Theme::GithubLight, 'dark' => Theme::GithubDark])->toString();
        }
        $label = self::LABELS[$language] ?? (in_array($language, ['', 'text', 'txt', 'plain'], true) ? '' : $language);

        return '<div class="code-block"' . ($label === '' ? '' : ' data-language="' . htmlspecialchars($label, ENT_QUOTES) . '"') . '>' . $pre . '</div>';
    }
}
