<?php

namespace App\Services\TextFingerprint;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Per-reader marks hidden in the text: every paragraph carries the reader's 32-bit code written
 * with two invisible characters (U+200B = 0, U+2060 = 1). The stored text stays clean; marks are
 * added only in API responses.
 *
 * The mark is placed right after an ordinary space, and ZWNJ (U+200C) is never touched,
 * so Persian spelling is unaffected.
 */
class TextFingerprint
{
    public const ZERO = "\u{200B}";

    public const ONE = "\u{2060}";

    /** Bits of the user's watermark_payload that make up the code (8 hex digits). */
    private const CODE_HEX_LENGTH = 8;

    private const BLOCK_TAGS = ['p', 'li', 'blockquote', 'h2', 'h3'];

    /** Add the user's mark to every block of the HTML body. */
    public function mark(string $html, User $user): string
    {
        $mark = $this->encode($this->codeFor($user));
        $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $marked = false;
        foreach ($parts as $i => $part) {
            if ($part[0] === '<') {
                if ($this->opensBlock($part)) {
                    $marked = false;
                }

                continue;
            }

            if (! $marked && ($position = strpos($part, ' ')) !== false) {
                $parts[$i] = substr($part, 0, $position + 1).$mark.substr($part, $position + 1);
                $marked = true;
            }
        }

        return implode('', $parts);
    }

    /**
     * Codes found in a piece of text (a leaked paragraph, copied from the app), most frequent first.
     *
     * @return array<int, string> 8-digit hex codes
     */
    public function extractCodes(string $text): array
    {
        preg_match_all('/['.self::ZERO.self::ONE.']+/u', $text, $runs);

        $codes = [];
        foreach ($runs[0] as $run) {
            foreach (array_chunk(mb_str_split($run), 32) as $chunk) {
                if (count($chunk) === 32) {
                    $code = $this->decode(implode('', $chunk));
                    $codes[$code] = ($codes[$code] ?? 0) + 1;
                }
            }
        }

        arsort($codes);

        return array_keys($codes);
    }

    /**
     * Users whose code appears in the text.
     *
     * @return Collection<int, User>
     */
    public function identify(string $text): Collection
    {
        $users = collect();

        foreach ($this->extractCodes($text) as $code) {
            $users = $users->merge(User::where('watermark_payload', 'like', $code.'%')->get());
        }

        return $users->unique('id')->values();
    }

    private function codeFor(User $user): string
    {
        return substr($user->watermark_payload, 0, self::CODE_HEX_LENGTH);
    }

    private function opensBlock(string $tag): bool
    {
        return (bool) preg_match('/^<('.implode('|', self::BLOCK_TAGS).')[\s>]/i', $tag);
    }

    private function encode(string $hex): string
    {
        $bits = '';
        foreach (str_split($hex) as $digit) {
            $bits .= str_pad(decbin(hexdec($digit)), 4, '0', STR_PAD_LEFT);
        }

        return strtr($bits, ['0' => self::ZERO, '1' => self::ONE]);
    }

    private function decode(string $mark): string
    {
        $bits = strtr($mark, [self::ZERO => '0', self::ONE => '1']);

        return implode('', array_map(fn (string $nibble) => dechex(bindec($nibble)), str_split($bits, 4)));
    }
}
