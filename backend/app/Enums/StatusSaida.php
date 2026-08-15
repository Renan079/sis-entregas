<?php

namespace App\Enums;

enum StatusSaida: string
{
    case PLANEJADA = 'PLANEJADA';
    case EM_ANDAMENTO = 'EM_ANDAMENTO';
    case FINALIZADA = 'FINALIZADA';
    case CANCELADA = 'CANCELADA';
}