<?php
/**
 * Audit Actions Enumeration (RN-16)
 */

namespace App\Enums;

class AuditActions
{
    const CREAR = 'crear';
    const ACTUALIZAR = 'actualizar';
    const ELIMINAR = 'eliminar';
    const TRANSICION_ESTADO = 'transicion_estado';
    const LOGIN = 'login';
    const LOGOUT = 'logout';
    const GENERAR_CUENTA = 'generar_cuenta';
    const PAGO_REGISTRADO = 'pago_registrado';
    const ACCESO_DENEGADO = 'acceso_denegado';
    const CAMBIO_CONTRASENA = 'cambio_contrasena';
    
    const ALL = [
        self::CREAR,
        self::ACTUALIZAR,
        self::ELIMINAR,
        self::TRANSICION_ESTADO,
        self::LOGIN,
        self::LOGOUT,
        self::GENERAR_CUENTA,
        self::PAGO_REGISTRADO,
        self::ACCESO_DENEGADO,
        self::CAMBIO_CONTRASENA,
    ];
}
