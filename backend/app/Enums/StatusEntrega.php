<?php

namespace App\Enums;

enum StatusEntrega: string
{
    case PENDENTE = 'PENDENTE';
    case PROGRAMADA = 'PROGRAMADA';
    case EM_SAIDA = 'EM_SAIDA';
    case PARCIAL = 'PARCIAL';
    case CONCLUIDA = 'CONCLUIDA';
    case NAO_ENTREGUE = 'NAO_ENTREGUE';
    case CANCELADA = 'CANCELADA';
}