<?php
/**
 * Convertit un fichier JS en ASCII pur.
 *
 *   - dans les CHAÎNES (simples, doubles, template literals) : les caractères
 *     non-ASCII deviennent leur échappement \uXXXX → la valeur RUNTIME est
 *     strictement identique, mais plus aucun octet > 127 n'est servi ;
 *   - dans les COMMENTAIRES : équivalent ASCII lisible.
 *
 * Objectif : garantir un affichage correct quel que soit le charset
 * annoncé par le serveur (le mojibake « âœ— » vient d'un double encodage
 * UTF-8 -> CP1252 -> UTF-8, impossible si le fichier est 100 % ASCII).
 *
 * Usage : php tmp_ascii.php <fichier.js>
 */

$fichier = $argv[1] ?? null;
if (!$fichier || !is_file($fichier)) {
    fwrite(STDERR, "Usage: php tmp_ascii.php <fichier.js>\n");
    exit(1);
}

$src = file_get_contents($fichier);

// Équivalents ASCII utilisés dans les commentaires.
$remplacements = [
    "\u{2014}" => '-', "\u{2013}" => '-', "\u{2026}" => '...',
    "\u{2192}" => '->', "\u{25B6}" => '>', "\u{25C0}" => '<',
    "\u{2713}" => 'OK', "\u{00B7}" => '*', "\u{00D7}" => 'x',
    "\u{00AB}" => '"', "\u{00BB}" => '"', "\u{2019}" => "'",
    "\u{201C}" => '"', "\u{201D}" => '"', "\u{00A0}" => ' ',
    "\u{00E9}" => 'e', "\u{00E8}" => 'e', "\u{00EA}" => 'e',
    "\u{00E0}" => 'a', "\u{00EE}" => 'i', "\u{00F4}" => 'o',
    "\u{00FB}" => 'u', "\u{00E7}" => 'c', "\u{00F2}" => 'o',
];

$len = strlen($src);
$out = '';
$i = 0;
$stack = ['C'];        // C=code, SQ/DQ=chaîne, TPL=template, TC=code dans ${}, LC/BC=commentaires
$brace = 0;            // profondeur d'accolades dans TC

while ($i < $len) {
    $st = $stack[count($stack) - 1];
    $c  = $src[$i];
    $nx = $src[$i + 1] ?? '';

    switch ($st) {
        case 'C':
        case 'TC':
            if ($c === '/' && $nx === '/') { $out .= '//'; $i++; $stack[] = 'LC'; continue 2; }
            if ($c === '/' && $nx === '*') { $out .= '/*'; $i++; $stack[] = 'BC'; continue 2; }
            if ($c === "'")  { $out .= $c; $stack[] = 'SQ';  $i++; continue 2; }
            if ($c === '"')  { $out .= $c; $stack[] = 'DQ';  $i++; continue 2; }
            if ($c === '`')  { $out .= $c; $stack[] = 'TPL'; $i++; continue 2; }
            if ($st === 'TC' && $c === '{') { $brace++; }
            if ($st === 'TC' && $c === '}') {
                $brace--;
                $out .= $c; $i++;
                if ($brace === 0) { array_pop($stack); }
                continue 2;
            }
            $out .= $c; $i++;
            continue 2;

        case 'SQ':
        case 'DQ':
            $out .= $c; $i++;
            if ($c === '\\') { if ($i < $len) { $out .= $src[$i]; $i++; } continue 2; }
            if (($st === 'SQ' && $c === "'") || ($st === 'DQ' && $c === '"')) { array_pop($stack); }
            continue 2;

        case 'TPL':
            if ($c === '\\') { $out .= $c; $i++; if ($i < $len) { $out .= $src[$i]; $i++; } continue 2; }
            if ($c === '$' && $nx === '{') {
                $out .= '${'; $i += 2; $stack[] = 'TC'; $brace = 1; continue 2;
            }
            $out .= escapeChar($c);
            $i++;
            continue 2;

        case 'LC':
            if ($c === "\n") { $out .= $c; $i++; array_pop($stack); continue 2; }
            $out .= commentChar($c, $remplacements);
            $i++;
            continue 2;

        case 'BC':
            if ($c === '*' && $nx === '/') { $out .= '*/'; $i += 2; array_pop($stack); continue 2; }
            $out .= commentChar($c, $remplacements);
            $i++;
            continue 2;
    }
}

file_put_contents($fichier, $out);

printf("Octets avant : %d | apres : %d\n", strlen($src), strlen($out));
printf("Non-ASCII restants : %d\n", preg_match_all('/[^\x00-\x7F]/', $out));
printf(" Echappements \\uXXXX : %d\n", preg_match_all('/\\\\u[0-9A-Fa-f]{4}/', $out));

/** Caractère non-ASCII dans une chaîne → échappement \uXXXX (valeur identique). */
function escapeChar(string $ch): string
{
    if (strlen($ch) === 1 && ord($ch) < 128) {
        return $ch;
    }
    return sprintf('\u%04X', mb_ord($ch, 'UTF-8'));
}

/** Caractère non-ASCII dans un commentaire → équivalent ASCII lisible. */
function commentChar(string $ch, array $remplacements): string
{
    if (strlen($ch) === 1 && ord($ch) < 128) {
        return $ch;
    }
    return $remplacements[$ch] ?? '';
}
