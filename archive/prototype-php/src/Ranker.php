<?php
declare(strict_types=1);

namespace TalentEngine;

/**
 * Classe les candidats. Ordre :
 *   1. profils qualifiés avant les non qualifiés ;
 *   2. score décroissant ;
 *   3. à score égal, preuves concrètes les plus fortes (le "faire" départage le "dire") ;
 *   4. puis premier arrivé, et enfin l'identifiant pour un ordre toujours stable.
 */
final class Ranker
{
    /** @param list<array> $candidates @return list<array> avec une clé `rank` (1 = premier) */
    public static function rank(array $candidates): array
    {
        usort($candidates, static fn(array $a, array $b) => self::key($b, $a) <=> self::key($a, $b));
        foreach (array_keys($candidates) as $i) {
            $candidates[$i]['rank'] = $i + 1;
        }
        return $candidates;
    }

    /** Clé de tri : les champs décroissants viennent de $desc, les croissants de $asc. */
    private static function key(array $desc, array $asc): array
    {
        return [
            (int) $desc['qualified'],
            $desc['score'],
            $desc['breakdown']['proof']['points'] ?? 0,
            $asc['created_at'],
            $asc['id'],
        ];
    }
}
