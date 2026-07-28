<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case DataEncoder = 'data_encoder';
    case ScannerOperator = 'scanner_operator';
    case StageOperator = 'stage_operator';
    case TechnicalOperator = 'technical_operator';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::DataEncoder => 'Data encoder',
            self::ScannerOperator => 'Scanner operator',
            self::StageOperator => 'Stage operator',
            self::TechnicalOperator => 'Technical operator',
        };
    }
}
