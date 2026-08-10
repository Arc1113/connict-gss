<?php

namespace App\Enums;

enum GraduateAudioSourceType: string
{
    case ManualUpload = 'manual_upload';
    case AzureTts = 'azure_tts';
    case LocalTts = 'local_tts';
}
