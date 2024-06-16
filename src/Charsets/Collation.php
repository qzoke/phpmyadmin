<?php
/**
 * Value object class for a collation
 */

declare(strict_types=1);

namespace PhpMyAdmin\Charsets;

use function __;
use function _pgettext;
use function explode;
use function implode;
use function in_array;

/**
 * Value object class for a collation
 */
final class Collation
{
    /**
     * A description of the collation
     */
    private string $description;

    /**
     * @param string $name         The collation name
     * @param string $charset      The name of the character set with which the collation is associated
     * @param int    $id           The collation ID
     * @param bool   $isDefault    Whether the collation is the default for its character set
     * @param bool   $isCompiled   Whether the character set is compiled into the server
     * @param int    $sortLength   Used for determining the memory used to sort strings in this collation
     * @param string $padAttribute The collation pad attribute
     */
    private function __construct(
        private string $name,
        private string $charset,
        private int $id,
        private bool $isDefault,
        private bool $isCompiled,
        private int $sortLength,
        private string $padAttribute,
    ) {
        $this->description = $this->buildDescription();
    }

    /** @param string[] $state State obtained from the database server */
    public static function fromServer(array $state): self
    {
        return new self(
            $state['Collation'] ?? '',
            $state['Charset'] ?? '',
            (int) ($state['Id'] ?? 0),
            isset($state['Default']) && ($state['Default'] === 'Yes' || $state['Default'] === '1'),
            isset($state['Compiled']) && ($state['Compiled'] === 'Yes' || $state['Compiled'] === '1'),
            (int) ($state['Sortlen'] ?? 0),
            $state['Pad_attribute'] ?? '',
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isCompiled(): bool
    {
        return $this->isCompiled;
    }

    public function getSortLength(): int
    {
        return $this->sortLength;
    }

    public function getPadAttribute(): string
    {
        return $this->padAttribute;
    }

    /**
     * Returns description for given collation
     *
     * @return string collation description
     */
    private function buildDescription(): string
    {
        $parts = explode('_', $this->getName());

        $name = __('Unknown');
        $variant = null;
        $suffixes = [];
        $unicode = false;
        $unknown = false;

        $level = 0;
        foreach ($parts as $part) {
            if ($level === 0) {
                /* Next will be language */
                $level = 1;
                /* First should be charset */
                [$name, $unicode, $unknown, $variant] = $this->getNameForLevel0($unicode, $unknown, $part, $variant);
                continue;
            }

            if ($level === 1) {
                /* Next will be variant unless changed later */
                $level = 4;
                /* Locale name or code */
                [$name, $level, $found] = $this->getNameForLevel1($unicode, $unknown, $part, $name, $level);
                if ($found) {
                    continue;
                }
                // Not parsed token, fall to next level
            }

            if ($level === 2) {
                /* Next will be variant */
                $level = 4;
                /* Germal variant */
                if ($part === 'pb') {
                    $name = _pgettext('Collation', 'German (phone book order)');
                    continue;
                }

                $name = _pgettext('Collation', 'German (dictionary order)');
                // Not parsed token, fall to next level
            }

            if ($level === 3) {
                /* Next will be variant */
                $level = 4;
                /* Spanish variant */
                if ($part === 'trad') {
                    $name = _pgettext('Collation', 'Spanish (traditional)');
                    continue;
                }

                $name = _pgettext('Collation', 'Spanish (modern)');
                // Not parsed token, fall to next level
            }

            if ($level === 4) {
                /* Next will be suffix */
                $level = 5;
                /* Variant */
                $variantFound = $this->getVariant($part);
                if ($variantFound !== null) {
                    $variant = $variantFound;
                    continue;
                }
                // Not parsed token, fall to next level
            }

            if ($level < 5) {
                continue;
            }

            /* Suffixes */
            $suffix = $this->addSuffixes($part);
            if ($suffix === null) {
                continue;
            }

            $suffixes[] = $suffix;
        }

        return $this->buildName($name, $variant, $suffixes);
    }

    /** @param string[] $suffixes */
    private function buildName(string $result, string|null $variant, array $suffixes): string
    {
        if ($variant !== null) {
            $result .= ' (' . $variant . ')';
        }

        if ($suffixes !== []) {
            $result .= ', ' . implode(', ', $suffixes);
        }

        return $result;
    }

    private function getVariant(string $part): string|null
    {
        return match ($part) {
            '0900' => 'UCA 9.0.0',
            '520' => 'UCA 5.2.0',
            'mysql561' => 'MySQL 5.6.1',
            'mysql500' => 'MySQL 5.0.0',
            default => null,
        };
    }

    private function addSuffixes(string $part): string|null
    {
        return match ($part) {
            'ci' => _pgettext('Collation variant', 'case-insensitive'),
            'cs' => _pgettext('Collation variant', 'case-sensitive'),
            'ai' => _pgettext('Collation variant', 'accent-insensitive'),
            'as' => _pgettext('Collation variant', 'accent-sensitive'),
            'ks' => _pgettext('Collation variant', 'kana-sensitive'),
            'w2','l2' => _pgettext('Collation variant', 'multi-level'),
            'bin' => _pgettext('Collation variant', 'binary'),
            'nopad' => _pgettext('Collation variant', 'no-pad'),
            default => null,
        };
    }

    /**
     * @return array<int, bool|string|null>
     * @psalm-return array{string, bool, bool, string|null}
     */
    private function getNameForLevel0(
        bool $unicode,
        bool $unknown,
        string $part,
        string|null $variant,
    ): array {


        $name = match ($part) {
            'binary' => _pgettext('Collation', 'Binary'),
            'utf8mb4', 'ucs2', 'utf8', 'utf8mb3', 'utf16', 'utf16le', 'utf16be', 'utf32' => _pgettext('Collation', 'Unicode'),
            'ascii', 'cp850', 'dec8', 'hp8', 'latin1', 'macroman' => _pgettext('Collation', 'West European'),
            'cp1250', 'cp852', 'latin2', 'macce' => _pgettext('Collation', 'Central European'),
            'cp866', 'koi8r' => _pgettext('Collation', 'Russian'),
            'gb2312', 'gbk' => _pgettext('Collation', 'Simplified Chinese'),
            'big5' => _pgettext('Collation', 'Traditional Chinese'),
            'gb18030' => _pgettext('Collation', 'Chinese'),
            'sjis', 'ujis', 'cp932', 'eucjpms' => _pgettext('Collation', 'Japanese'),
            'cp1257', 'latin7' => _pgettext('Collation', 'Baltic'),
            'armscii8', 'armscii' => _pgettext('Collation', 'Armenian'),
            'cp1251' => _pgettext('Collation', 'Cyrillic'),
            'cp1256' => _pgettext('Collation', 'Arabic'),
            'euckr' => _pgettext('Collation', 'Korean'),
            'hebrew' => _pgettext('Collation', 'Hebrew'),
            'geostd8' => _pgettext('Collation', 'Georgian'),
            'greek' => _pgettext('Collation', 'Greek'),
            'keybcs2' => _pgettext('Collation', 'Czech-Slovak'),
            'koi8u' => _pgettext('Collation', 'Ukrainian'),
            'latin5' => _pgettext('Collation', 'Turkish'),
            'swe7' => _pgettext('Collation', 'Swedish'),
            'tis620' => _pgettext('Collation', 'Thai'),
            default => _pgettext('Collation', 'Unknown'),
        };

        switch ($part) {
            case 'utf8mb4':
                $variant = 'UCA 4.0.0';
                $unicode = true;
                break;
            case 'ucs2':
            case 'utf8':
            case 'utf8mb3':
            case 'utf16':
            case 'utf16le':
            case 'utf16be':
            case 'utf32':
            case 'gb18030':
                $unicode = true;
                break;
        }

        if ($name === _pgettext('Collation', 'Unknown')) {
            $unknown = true;
        }

        return [$name, $unicode, $unknown, $variant];
    }

    /**
     * @return array<int, bool|int|string>
     * @psalm-return array{string, int, bool}
     */
    private function getNameForLevel1(
        bool $unicode,
        bool $unknown,
        string $part,
        string $name,
        int $level,
    ): array {
        $found = true;

        $name = match ($part) {
            // 'general' => null,
            'bulgarian', 'bg' => _pgettext('Collation', 'Bulgarian'),
            // 'chinese', 'cn', 'zh' => $unicode ? _pgettext('Collation', 'Chinese') : null,
            'croatian', 'hr' => _pgettext('Collation', 'Croatian'),
            'czech', 'cs' => _pgettext('Collation', 'Czech'),
            'danish', 'da' => _pgettext('Collation', 'Danish'),
            'english', 'en' => _pgettext('Collation', 'English'),
            'esperanto', 'eo' => _pgettext('Collation', 'Esperanto'),
            'estonian', 'et' => _pgettext('Collation', 'Estonian'),
            'german1' => _pgettext('Collation', 'German (dictionary order)'),
            'german2' => _pgettext('Collation', 'German (phone book order)'),
            // 'german', 'de' => null, /* Name is set later */
            'hungarian', 'hu' => _pgettext('Collation', 'Hungarian'),
            'icelandic', 'is' => _pgettext('Collation', 'Icelandic'),
            'japanese', 'ja' => _pgettext('Collation', 'Japanese'),
            'la' => _pgettext('Collation', 'Classical Latin'),
            'latvian', 'lv' => _pgettext('Collation', 'Latvian'),
            'lithuanian', 'lt' => _pgettext('Collation', 'Lithuanian'),
            'korean', 'ko' => _pgettext('Collation', 'Korean'),
            'myanmar', 'my' => _pgettext('Collation', 'Burmese'),
            'persian' => _pgettext('Collation', 'Persian'),
            'polish', 'pl' => _pgettext('Collation', 'Polish'),
            'roman' => _pgettext('Collation', 'West European'),
            'romanian', 'ro' => _pgettext('Collation', 'Romanian'),
            'ru' => _pgettext('Collation', 'Russian'),
            'si', 'sinhala' => _pgettext('Collation', 'Sinhalese'),
            'slovak', 'sk' => _pgettext('Collation', 'Slovak'),
            'slovenian', 'sl' => _pgettext('Collation', 'Slovenian'),
            'spanish' => _pgettext('Collation', 'Spanish (modern)'),
            // 'es' => null,/* Name is set later */
            'spanish2' => _pgettext('Collation', 'Spanish (traditional)'),
            'swedish', 'sv' => _pgettext('Collation', 'Swedish'),
            'thai', 'th' => _pgettext('Collation', 'Thai'),
            'turkish', 'tr' => _pgettext('Collation', 'Turkish'),
            'ukrainian', 'uk' => _pgettext('Collation', 'Ukrainian'),
            'vietnamese', 'vi' => _pgettext('Collation', 'Vietnamese'),
            //'unicode' => $unknown ? _pgettext('Collation', 'Unicode') : null,
            // default => null, /* $found = false; is implemented using in_array */
        };

        switch ($part) {
            case 'general':
                break;
            case 'chinese':
            case 'cn':
            case 'zh':
                if ($unicode) {
                    $name = _pgettext('Collation', 'Chinese');
                }
                break;
            case 'german':
            case 'de':
                /* Name is set later */
                $level = 2;
                break;
            case 'es':
                /* Name is set later */
                $level = 3;
                break;
            case 'unicode':
                if ($unknown) {
                    $name = _pgettext('Collation', 'Unicode');
                }
                break;
        }

        $validParts = [
            'general', 'bulgarian', 'bg', 'chinese', 'cn', 'zh',
            'croatian', 'hr', 'czech', 'cs', 'danish', 'da',
            'english', 'en', 'esperanto', 'eo', 'estonian', 'et',
            'german1', 'german2', 'german', 'de', 'hungarian', 'hu',
            'icelandic', 'is', 'japanese', 'ja', 'la', 'latvian', 'lv',
            'lithuanian', 'lt', 'korean', 'ko', 'myanmar', 'my',
            'persian', 'polish', 'pl', 'roman', 'romanian', 'ro',
            'ru', 'si', 'sinhala', 'slovak', 'sk', 'slovenian', 'sl',
            'spanish', 'es', 'spanish2', 'swedish', 'sv', 'thai', 'th',
            'turkish', 'tr', 'ukrainian', 'uk', 'vietnamese', 'vi', 'unicode'
        ];

        $found = in_array($part, $validParts, true);

        return [$name, $level, $found];
    }
}
