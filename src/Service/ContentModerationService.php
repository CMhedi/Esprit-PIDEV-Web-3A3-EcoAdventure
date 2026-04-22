<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContentModerationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $manualBadWordsEn,
        private readonly ?string $manualBadWordsFr,
        private readonly ?string $manualBadWordsAr
    ) {
    }

    /**
     * Returns true when content should be blocked.
     */
    public function containsProhibitedContent(string $text): bool
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return false;
        }

        if ($this->detectWithProfanityDev($normalized)) {
            return true;
        }

        return $this->detectWithManualLists($normalized);
    }

    /**
     * Manual bad-word matching only (no remote API call).
     * Useful for high-volume checks like filtering emoji labels.
     */
    public function containsManualProhibitedContent(string $text): bool
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return false;
        }

        return $this->detectWithManualLists($normalized);
    }

    private function detectWithProfanityDev(string $text): bool
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://vector.profanity.dev',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'message' => $text,
                    ],
                    'timeout' => 12,
                ]
            );

            $data = $response->toArray(false);
            $hasProfanity = $data['has_profanity']
                ?? $data['profanity']
                ?? $data['is_profane']
                ?? $data['isProfane']
                ?? $data['result']['is_profane']
                ?? null;

            if (is_bool($hasProfanity)) {
                return $hasProfanity;
            }

            $censored = (string) ($data['censored'] ?? $data['filtered'] ?? '');
            if ($censored !== '' && $censored !== $text && preg_match('/[*#_]{2,}/', $censored)) {
                return true;
            }
        } catch (\Throwable) {
            // Fall through to manual detection.
        }

        return false;
    }

    private function detectWithManualLists(string $text): bool
    {
        $terms = array_merge(
            $this->parseCsvTerms((string) ($this->manualBadWordsEn ?? '')),
            $this->parseCsvTerms((string) ($this->manualBadWordsFr ?? '')),
            $this->parseCsvTerms((string) ($this->manualBadWordsAr ?? '')),
            ['shit', 'bastard', 'fuck', 'asshole', 'dick', 'salope', 'putain', 'connard', 'zebi', '???', '???', '?????', '😁']
        );

        if ($terms === []) {
            return false;
        }

        $haystack = mb_strtolower($text);
        $normalizedHaystack = $this->normalizeForModeration($haystack);

        $uniqueTerms = array_values(array_unique(array_filter(array_map(
            static fn (string $term): string => mb_strtolower(trim($term)),
            $terms
        ), static fn (string $term): bool => $term !== '')));

        foreach ($uniqueTerms as $term) {
            $normalizedTerm = $this->normalizeForModeration($term);

            if ($term !== '' && mb_stripos($haystack, $term) !== false) {
                return true;
            }
            if ($normalizedTerm !== '' && $normalizedHaystack !== '' && str_contains($normalizedHaystack, $normalizedTerm)) {
                return true;
            }
            if ($this->matchesObfuscatedTerm($text, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function parseCsvTerms(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }

        $parts = array_map(static fn (string $item): string => trim($item), explode(',', $csv));
        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }

    private function normalizeForModeration(string $text): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? '';
        if ($clean === '') {
            return '';
        }

        $collapsed = preg_replace('/(.)\1+/u', '$1', $clean) ?? $clean;
        return mb_strtolower($collapsed);
    }

    private function matchesObfuscatedTerm(string $text, string $term): bool
    {
        $term = trim($term);
        if ($term === '') {
            return false;
        }

        $chars = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars) || $chars === []) {
            return false;
        }

        $parts = [];
        foreach ($chars as $char) {
            $parts[] = $this->buildCharClass($char) . '+';
        }

        $pattern = '/(?:^|[^\p{L}\p{N}])' . implode('[\s\-_\.]*', $parts) . '(?:$|[^\p{L}\p{N}])/iu';
        return (bool) preg_match($pattern, $text);
    }

    private function buildCharClass(string $char): string
    {
        $escaped = preg_quote($char, '/');
        $lower = mb_strtolower($char);

        return match ($lower) {
            'a' => '[a4@]',
            'b' => '[b8]',
            'e' => '[e3]',
            'g' => '[g69]',
            'i' => '[i1!|l]',
            'l' => '[l1!|i]',
            'o' => '[o0]',
            's' => '[s5$]',
            't' => '[t7+]',
            'z' => '[z2]',
            default => '[' . $escaped . ']',
        };
    }
}
