<?php

namespace App\Enums;

enum StatusPendencia: string
{
    case ABERTA = 'ABERTA';
    case PROGRAMADA = 'PROGRAMADA';
    case RESOLVIDA = 'RESOLVIDA';
    case CANCELADA = 'CANCELADA';
}