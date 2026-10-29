<?php

declare(strict_types=1);

namespace App\Markdown;

use League\CommonMark\Normalizer\TextNormalizerInterface;

/**
 * Heading IDs as GitHub makes them, so a link such as SESSIONS.md#csrf-protection that works
 * on GitHub also works on the site: lowercase, punctuation removed (but not hyphens or
 * underscores), and each space a hyphen. CommonMark adds -1, -2 to repeated IDs, as GitHub does.
 */
final class GithubSlugNormalizer implements TextNormalizerInterface
{
    /** @param array<string, mixed> $context */
    public function normalize(string $text, array $context = []): string
    {
        $slug = mb_strtolower(trim($text), 'UTF-8');
        $slug = (string) preg_replace('/[^\p{L}\p{M}\p{N}\p{Pc}\- ]/u', '', $slug);

        return str_replace(' ', '-', $slug);
    }
}
