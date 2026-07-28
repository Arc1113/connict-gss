<?php

namespace App\Enums;

enum StageAudioStatus: string
{
    case Stopped = 'stopped';
    case Playing = 'playing';
    case Paused = 'paused';
    case Failed = 'failed';
}
