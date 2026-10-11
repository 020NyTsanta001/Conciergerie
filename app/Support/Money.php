<?php

namespace App\Support;

/** Petites fonctions de calcul et d'affichage des montants (en Ariary, entiers). */
final class Money
{
    public static function round(float|int $ar): int
    {
        return (int) round($ar);
    }

    public static function deposit(float|int $total): int
    {
        return self::round($total * config('money.deposit_rate'));
    }

    public static function commission(float|int $total): int
    {
        return self::round($total * config('money.commission_rate'));
    }

    public static function format(float|int $ar): string
    {
        return number_format($ar, 0, ',', ' ').' Ar';
    }
}