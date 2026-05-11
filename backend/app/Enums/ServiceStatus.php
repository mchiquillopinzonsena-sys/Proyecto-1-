<?php
/**
 * Service Status Enumeration
 */

namespace App\Enums;

class ServiceStatus
{
    const COTIZADO = 'cotizado';
    const CONFIRMADO = 'confirmado';
    const EN_PROGRESO = 'en_progreso';
    const COMPLETADO = 'completado';
    const CANCELADO = 'cancelado';
    const POSPUESTO = 'pospuesto';
    
    const ALL = [
        self::COTIZADO,
        self::CONFIRMADO,
        self::EN_PROGRESO,
        self::COMPLETADO,
        self::CANCELADO,
        self::POSPUESTO,
    ];
    
    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL);
    }
}
