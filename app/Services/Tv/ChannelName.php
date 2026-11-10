<?php

namespace App\Services\Tv;

/**
 * Channel names as the provider writes them ("6TER .c", "13 EME RUE HD .s", "BEIN SPORTS 1 (BACKUP)")
 * cleaned for display ({@see clean}), and one key per channel name, so the provider's channels, the programme guides and the logo library
 * name the same channel the same way: "TF1 HD", "TF1.fr", "FR | TF1" and "tf1-fr.png" are "tf1";
 * "Canal+" is "canalplus"; "BFMTV" and "BFM TV" are "bfm".
 */
class ChannelName
{
    /** Words that don't name the channel ("TF1 HD" = "TF1"). */
    private const NOISE = ['hd', 'fhd', 'uhd', '4k', 'sd', 'hevc', 'h265', 'h264', '1080p', '720p', '1080', '720', '50fps', 'backup', 'fr', 'tv', 'french'];

    /** Quality tags: not part of the name shown to the viewer. */
    private const TAGS = '(?:hd|fhd|uhd|4k|sd|hevc|h\.?26[45]|1080p?|720p?|50fps|backup|raw)';

    /**
     * The name shown in the apps: without the provider's mirror suffix (" .c", " .s", " .b"),
     * quality tags ("HD", "FHD", "(1080P)", "(BACKUP)") and extra spaces. "6TER .c" → "6TER",
     * "13 EME RUE HD .s" → "13 EME RUE". Never empty: a name made only of tags stays as is.
     */
    public static function clean(string $name): string
    {
        $clean = $name;
        // Mirror suffixes, possibly several: "TF1 HD .c .s".
        while (($next = preg_replace('/\s+\.[a-z0-9]{1,3}\s*$/iu', '', $clean)) !== null && $next !== $clean) {
            $clean = $next;
        }
        // Tags in brackets or parentheses, then as plain words.
        $clean = preg_replace('/\s*[\(\[]\s*'.self::TAGS.'(?:\s*[\/|,+-]?\s*'.self::TAGS.')*\s*[\)\]]/iu', ' ', $clean) ?? $clean;
        $clean = preg_replace('/(?<![\p{L}\p{N}])'.self::TAGS.'(?![\p{L}\p{N}])/iu', ' ', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean, " \t-|:.");

        return $clean === '' ? trim($name) : $clean;
    }

    public static function key(string $name): string
    {
        return self::join(self::words($name));
    }

    /**
     * Keys to try when the full name matches nothing, for logos only (never for the programmes,
     * which would be another channel's): the name without its last words, longest first, at least
     * 4 letters. "RMC SPORT LIVE 16" → "rmcsportlive", "rmcsport"; "A LA CARTE 10" → "alacarte".
     *
     * @return list<string>
     */
    public static function fallbackKeys(string $name): array
    {
        $words = self::words($name);
        $keys = [];
        for ($n = count($words) - 1; $n >= 1; $n--) {
            $key = self::join(array_slice($words, 0, $n));
            if (strlen($key) >= 4) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /** @param  list<string>  $words */
    private static function join(array $words): string
    {
        $key = implode('', $words);

        // "BFMTV" = "BFM TV" (but "MTV" stays "mtv").
        return strlen($key) > 4 && str_ends_with($key, 'tv') ? substr($key, 0, -2) : $key;
    }

    /**
     * The words that name the channel, normalised.
     *
     * @return list<string>
     */
    private static function words(string $name): array
    {
        $name = mb_strtolower(self::clean($name));
        // Whatever is still in brackets doesn't name the channel ("BEIN SPORTS 1 (B)").
        $name = trim(preg_replace('/\s*[\(\[][^\)\]]*[\)\]]?\s*$/u', '', $name) ?? $name) ?: $name;
        // A guide id: "TF1.fr" (country suffix).
        $name = preg_replace('/\.[a-z]{2}$/', '', $name) ?? $name;
        // A panel prefix: "FR | TF1", "FR: TF1".
        $name = preg_replace('/^[a-z]{2,3}\s*[|:]\s*/', '', $name) ?? $name;
        $name = strtr($name, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '+' => ' plus ', '’' => '', "'" => '',
        ]);
        // "13ème", "13eme", "13e" are the same ("13 EME RUE" = "13e rue").
        $name = preg_replace('/\b(\d+)\s*(?:eme|e)\b/', '$1e', $name) ?? $name;
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // "&", "et", "and" don't name the channel; singular = plural ("TF1 SERIES & FILM" =
        // "TF1 Séries Films" = "tf1-series-films-fr.png").
        $words = array_map(
            fn (string $w) => strlen($w) > 3 && $w !== 'plus' && str_ends_with($w, 's') ? substr($w, 0, -1) : $w,
            array_values(array_filter($words, fn (string $w) => ! in_array($w, ['et', 'and'], true)))
        );
        $kept = array_values(array_filter($words, fn (string $w) => ! in_array($w, self::NOISE, true)));

        return $kept === [] ? $words : $kept;
    }
}
