<?php

namespace App\Enums;

enum ResultadoEntrega: string
{
    case COMPLETA = 'COMPLETA';
    case PARCIAL = 'PARCIAL';
    case NAO_ENTREGUE = 'NAO_ENTREGUE';
}