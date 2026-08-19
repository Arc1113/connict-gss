<?php

namespace App\Enums;

enum PronunciationRuleSection: string
{
    case Any = 'any';
    case Name = 'name';
    case Program = 'program';
    case Major = 'major';
    case Honors = 'honors';
    case Award = 'award';
    case Template = 'template';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Any => 'Any announcement section',
            self::Name => 'Graduate name',
            self::Program => 'Program',
            self::Major => 'Major',
            self::Honors => 'Honors',
            self::Award => 'Awards',
            self::Template => 'Template wording',
            self::Additional => 'Additional wording',
        };
    }
}

