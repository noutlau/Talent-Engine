<?php

namespace App\Enums;

/** Liste [valeur => libellé] pour les formulaires et la validation. */
trait HasOptions
{
    abstract public function label(): string;

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
