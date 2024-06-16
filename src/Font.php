<?php
/**
 * Class with Font related methods.
 */

declare(strict_types=1);

namespace PhpMyAdmin;

use function ceil;
use function is_array;
use function mb_strlen;
use function mb_strtolower;
use function preg_replace;
use function str_replace;

/**
 * Class with Font related methods.
 */
class Font
{
    /**
     * Get list with characters and the corresponding width modifiers.
     *
     * @return (float|string[])[][] with characters and corresponding width modifier
     */
    public function getCharLists(): array
    {
        return [
            //ijl
            ['chars' => ['i', 'j', 'l'], 'modifier' => 0.23],
            //f
            ['chars' => ['f'], 'modifier' => 0.27],
            //tI
            ['chars' => ['t', 'I'], 'modifier' => 0.28],
            //r
            ['chars' => ['r'], 'modifier' => 0.34],
            //1
            ['chars' => ['1'], 'modifier' => 0.49],
            //cksvxyzJ
            ['chars' => ['c', 'k', 's', 'v', 'x', 'y', 'z', 'J'], 'modifier' => 0.5],
            //abdeghnopquL023456789
            [
                'chars' => [
                    'a',
                    'b',
                    'd',
                    'e',
                    'g',
                    'h',
                    'n',
                    'o',
                    'p',
                    'q',
                    'u',
                    'L',
                    '0',
                    '2',
                    '3',
                    '4',
                    '5',
                    '6',
                    '7',
                    '8',
                    '9',
                ],
                'modifier' => 0.56,
            ],
            //FTZ
            ['chars' => ['F', 'T', 'Z'], 'modifier' => 0.61],
            //ABEKPSVXY
            ['chars' => ['A', 'B', 'E', 'K', 'P', 'S', 'V', 'X', 'Y'], 'modifier' => 0.67],
            //wCDHNRU
            ['chars' => ['w', 'C', 'D', 'H', 'N', 'R', 'U'], 'modifier' => 0.73],
            //GOQ
            ['chars' => ['G', 'O', 'Q'], 'modifier' => 0.78],
            //mM
            ['chars' => ['m', 'M'], 'modifier' => 0.84],
            //W
            ['chars' => ['W'], 'modifier' => 0.95],
            //" "
            ['chars' => [' '], 'modifier' => 0.28],
        ];
    }

    /**
     * Get width of string/text
     *
     * The text element width is calculated depending on font name
     * and font size.
     *
     * @param string       $text      string of which the width will be calculated
     * @param string       $font      name of the font like Arial,sans-serif etc
     * @param int          $fontSize  size of font
     * @param mixed[]|null $charLists list of characters and their width modifiers
     *
     * @return int width of the text
     */
    public function getStringWidth(
        string $text,
        string $font,
        int $fontSize,
        array|null $charLists = null,
    ): int {
        if (
            ! isset($charLists[0]['chars'], $charLists[0]['modifier'])
            || ! is_array($charLists[0]['chars'])
        ) {
            $charLists = $this->getCharLists();
        }

        // Start by counting the width, giving each character a modifying value
        $count = 0;

        foreach ($charLists as $charList) {
            $count += (mb_strlen($text)
                - mb_strlen(str_replace($charList['chars'], '', $text))
                ) * $charList['modifier'];
        }

        $text = str_replace(' ', '', $text);//remove the " "'s
        //all other chars
        $count += mb_strlen((string) preg_replace('/[a-z0-9]/i', '', $text)) * 0.3;

        $font = mb_strtolower($font);

        $modifier = match ($font) {
            'arial', 'sans-serif' => 1,
            'times', 'serif', 'brushscriptstd', 'californian fb' => 0.92,
            'broadway' => 1.23,
            default => 1,
        };

        $textWidth = $count * $fontSize;

        return (int) ceil($textWidth * $modifier);
    }
}
