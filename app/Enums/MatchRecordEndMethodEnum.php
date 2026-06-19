<?php

namespace App\Enums;

enum MatchRecordEndMethodEnum: string
{
    case VICTORY_UNANIMOUS_DECISION = 'victory_unanimous_decision';
    case VICTORY_SPLIT_DECISION = 'victory_split_decision';
    case VICTORY_KO = 'victory_ko';
    case VICTORY_TKO = 'victory_tko';
    case VICTORY_DISQUALIFICATION = 'victory_disqualification';
    case DRAW = 'draw';
    case NO_CONTEST = 'no_contest';

    public function label(): string
    {
        return match ($this) {
            self::VICTORY_UNANIMOUS_DECISION => __('enums.match_record_end_method.victory_unanimous_decision'),
            self::VICTORY_SPLIT_DECISION => __('enums.match_record_end_method.victory_split_decision'),
            self::VICTORY_KO => __('enums.match_record_end_method.victory_ko'),
            self::VICTORY_TKO => __('enums.match_record_end_method.victory_tko'),
            self::VICTORY_DISQUALIFICATION => __('enums.match_record_end_method.victory_disqualification'),
            self::DRAW => __('enums.match_record_end_method.draw'),
            self::NO_CONTEST => __('enums.match_record_end_method.no_contest'),
        };
    }
}
