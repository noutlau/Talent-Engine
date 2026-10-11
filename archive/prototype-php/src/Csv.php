<?php
declare(strict_types=1);

namespace TalentEngine;

final class Csv
{
    /**
     * Lit un CSV avec ligne d'en-tête. Détecte le séparateur (`,` ou `;`, cas d'Excel en français).
     * @return list<array<string, string>> lignes indexées par nom de colonne, numéro de ligne dans `_line`
     */
    public static function read(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Impossible d'ouvrir le fichier : $path");
        }
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // BOM UTF-8
        $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $headers = array_map('trim', str_getcsv(trim($first), $separator, '"', ''));

        $rows = [];
        $line = 1;
        while (($fields = fgetcsv($handle, null, $separator, '"', '')) !== false) {
            $line++;
            if ($fields === [null]) {
                continue; // ligne vide
            }
            $fields = array_pad(array_slice($fields, 0, count($headers)), count($headers), '');
            $rows[] = array_combine($headers, $fields) + ['_line' => $line];
        }
        fclose($handle);
        return $rows;
    }

    /** Neutralise l'injection de formules quand le fichier exporté est ouvert dans Excel. */
    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
