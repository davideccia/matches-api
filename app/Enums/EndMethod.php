<?php

namespace App\Enums;

enum EndMethod: string
{
    case VICTORY_UNANIMOUS_DECISION = 'victory_unanimous_decision';
    case VICTORY_SPLIT_DECISION = 'victory_split_decision';
    case VICTORY_KO = 'victory_ko';
    case VICTORY_TKO = 'victory_tko';
    case VICTORY_DISQUALIFICATION = 'victory_disqualification';
    case DRAW = 'draw';
    case NO_CONTEST = 'no_contest';
}
