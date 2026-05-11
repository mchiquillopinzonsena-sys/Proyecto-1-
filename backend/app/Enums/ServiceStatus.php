<?php

/**
 * Estados alineados con database/migrations/005_create_servicios_table.sql
 */

namespace App\Enums;

class ServiceStatus
{
    public const PENDIENTE = 'pendiente';

    public const PROGRAMADO = 'programado';

    public const EN_PROCESO = 'en_proceso';

    public const COMPLETADO = 'completado';

    public const CANCELADO = 'cancelado';

    public const ALL = [
        self::PENDIENTE,
        self::PROGRAMADO,
        self::EN_PROCESO,
        self::COMPLETADO,
        self::CANCELADO,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }
}
