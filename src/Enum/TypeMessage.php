<?php

namespace App\Enum;

enum TypeMessage: string
{
    case TEXTE = 'TEXTE';
    // Backward compatibility for legacy DB rows using TEXT
    case TEXT = 'TEXT';
    case EMOJI = 'EMOJI';
       case VOCALE = 'VOCALE';
    case IMAGE = 'IMAGE';
    case VIDEO = 'VIDEO';
    case AUDIO = 'AUDIO';
    case PDF = 'PDF';
    case GIF = 'GIF';
    case APPEL_AUDIO = 'APPEL_AUDIO';
    case APPEL_VIDEO = 'APPEL_VIDEO';
}
