<?php

namespace App\Services\Tv;

/**
 * One key per channel name, so the provider's channels, the programme guides and the logo library
 * name the same channel the same way: "TF1 HD", "TF1.fr", "FR | TF1" and "tf1-fr.png" are "tf1";
 * "Canal+" is "canalplus"; "BFMTV" and "BFM TV" are "bfm".
 */
class ChannelName
{
    /** Words that don't name the channel ("TF1 HD" = "TF1"). */
    private const NOISE = ['hd', 'fhd', 'uhd', '4k', 'sd', 'hevc', 'h265', 'h264', '1080p', '720p', '1080', '720', '50fps', 'backup', 'fr', 'tv'];

    public static function key(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // A guide id: "TF1.fr" (country suffix).
        $name = preg_replace('/\.[a-z]{2}$/', '', $name) ?? $name;
        // A panel prefix: "FR | TF1", "FR: TF1".
        $name = preg_replace('/^[a-z]{2,3}\s*[|:]\s*/', '', $name) ?? $name;
        $name = strtr($name, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '+' => ' plus ', '’' => '', "'" => '',
        ]);
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = array_filter($words, fn (string $w) => ! in_array($w, self::NOISE, true));
        $key = implode('', $kept === [] ? $words : $kept);

        // "BFMTV" = "BFM TV" (but "MTV" stays "mtv").
        return strlen($key) > 4 && str_ends_with($key, 'tv') ? substr($key, 0, -2) : $key;
    }
}
