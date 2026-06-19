<?php

namespace App\Enums;

enum TournamentPdfTypeEnum: string
{
    case SIMPLE = 'simple';
    case DETAILED = 'detailed';

    public function viewName(): string
    {
        return match ($this) {
            self::SIMPLE => 'pdf.tournament-simple',
            self::DETAILED => 'pdf.tournament-detailed',
        };
    }
}
