<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Search;

defined('ABSPATH') || exit;

/**
 * Text as the search compares it, the same for what is indexed and what is searched (SRCH-06a).
 *
 * Arabic is written in several equal ways, and a shopper types whichever comes first: the forms of alef with hamza or
 * madda (أ إ آ ٱ) read as ا, taa marbuta (ة) as ه, alef maqsura (ى) as ي, the hamza seats ؤ and ئ as و and ي; the
 * short vowels and other marks and the stretching tatweel are dropped; Arabic-Indic digits read as 0 to 9. Latin text
 * is lower-cased and its accents dropped. Anything that is not a letter or a digit separates words.
 *
 * This is the equality every Arabic search needs, not typo tolerance: a misspelling is found only when the curated
 * synonym list names it (SRCH-06, the boundary of Annex A section 1.2).
 *
 * Register ids: SRCH-06a. See specs/058-search.
 */
final class Normalise
{
    public static function text(string $text): string
    {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text);
        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $text = mb_strtolower(remove_accents($text), 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> The words of a text, normalised. */
    public static function words(string $text): array
    {
        $text = self::text($text);

        return $text === '' ? [] : array_values(array_unique(explode(' ', $text)));
    }
}
