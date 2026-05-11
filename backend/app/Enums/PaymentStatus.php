<?php
/**
 * Payment/Account Status Enumeration
 */

namespace App\Enums;

class PaymentStatus
{
    const PENDIENTE = 'pendiente';
    const PARCIALMENTE_PAGADA = 'parcialmente_pagada';
    const PAGADA = 'pagada';
    const VENCIDA = 'vencida';
    const CANCELADA = 'cancelada';
    
    const ALL = [
        self::PENDIENTE,
        self::PARCIALMENTE_PAGADA,
        self::PAGADA,
        self::VENCIDA,
        self::CANCELADA,
    ];
    
    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL);
    }
}
