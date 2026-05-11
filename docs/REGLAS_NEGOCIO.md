# 📋 Reglas de Negocio - Intérmica S.A.S

Documento técnico de Arquitectura v2.0 - Implementación de Reglas de Negocio

## RN-02: Actualización Automática de Stock

### Descripción
Cuando se completa un servicio que consume equipos/materiales, el stock se actualiza automáticamente.

### Implementación

**Backend Service:**
```php
// app/Services/StockService.php
public function actualizarStockPorServicio($servicioId) {
    $servicio = Servicio::find($servicioId);
    
    foreach ($servicio->items as $item) {
        $stock = Stock::where('producto_id', $item->producto_id)
                      ->lockForUpdate()
                      ->first();
        
        $stock->cantidad -= $item->cantidad_consumida;
        $stock->save();
        
        // Registrar movimiento
        MovimientoStock::create([
            'stock_id' => $stock->id,
            'tipo' => 'salida',
            'cantidad' => $item->cantidad_consumida,
            'razon' => "Servicio SVC-{$servicioId}",
            'usuario_id' => Auth::id(),
        ]);
    }
}
```

**Trigger MySQL:**
```sql
CREATE TRIGGER tg_actualizar_stock
AFTER UPDATE ON servicios
FOR EACH ROW
BEGIN
    IF NEW.estado = 'completado' AND OLD.estado != 'completado' THEN
        INSERT INTO movimientos_stock (stock_id, tipo, cantidad, razon)
        SELECT si.producto_id, 'salida', si.cantidad, 
               CONCAT('Servicio ', NEW.id)
        FROM servicios_items si
        WHERE si.servicio_id = NEW.id;
        
        UPDATE stock SET cantidad = cantidad - si.cantidad
        WHERE id IN (
            SELECT producto_id FROM servicios_items WHERE servicio_id = NEW.id
        );
    END IF;
END;
```

---

## RN-06: Generación Automática de Cuentas de Cobro (CC-YYYY-XXXX)

### Descripción
Al completar un servicio, se genera automáticamente una cuenta de cobro con formato único CC-YYYY-XXXX.

### Formato
- **CC**: Prefijo de Cuenta de Cobro
- **YYYY**: Año actual (2026)
- **XXXX**: Secuencial auto-incrementable (0001-9999)

**Ejemplo:** `CC-2026-0001`, `CC-2026-0002`

### Implementación

**Backend Service:**
```php
// app/Services/CuentaCobroService.php
public function generarCuenta($serviciosIds) {
    $año = date('Y');
    
    // Obtener siguiente secuencial del año
    $ultimaCuenta = CuentaCobro::whereYear('fecha_creacion', $año)
                                ->orderBy('id', 'desc')
                                ->first();
    
    $secuencial = ($ultimaCuenta ? 
                   intval(substr($ultimaCuenta->numero, -4)) : 0) + 1;
    
    $numero = sprintf('CC-%d-%04d', $año, $secuencial);
    
    $cuentaCobro = CuentaCobro::create([
        'numero' => $numero,
        'cliente_id' => $servicios[0]->cliente_id,
        'estado' => 'pendiente',
        'fecha_emision' => now(),
        'fecha_vencimiento' => now()->addDays(30),
    ]);
    
    // Calcular totales desde servicios
    $this->calcularTotales($cuentaCobro, $serviciosIds);
    
    // RN-16: Auditar
    AuditoriaService::registrar('crear', 'CuentaCobro', $cuentaCobro->id);
    
    // Generar PDF + QR
    DocumentoService::generarPDF($cuentaCobro);
    DocumentoService::generarQR($cuentaCobro);
    
    return $cuentaCobro;
}

private function calcularTotales($cuentaCobro, $serviciosIds) {
    $subtotal = 0;
    
    foreach ($serviciosIds as $servicioId) {
        $servicio = Servicio::find($servicioId);
        
        $itemSubtotal = $servicio->items->sum(fn($i) => $i->valor_total);
        $subtotal += $itemSubtotal;
        
        // Crear items en cuenta
        CuentaItem::create([
            'cuenta_cobro_id' => $cuentaCobro->id,
            'servicio_id' => $servicioId,
            'descripcion' => $servicio->descripcion,
            'subtotal' => $itemSubtotal,
        ]);
    }
    
    $iva = $subtotal * 0.19; // 19% IVA Colombia
    $total = $subtotal + $iva;
    
    $cuentaCobro->update([
        'subtotal' => $subtotal,
        'impuesto_iva' => $iva,
        'total' => $total,
    ]);
}
```

**Endpoint de Generación:**
```php
// POST /api/v1/cuentas
public function store(Request $request) {
    $validated = $request->validate([
        'servicios_ids' => 'required|array|min:1',
        'servicios_ids.*' => 'integer|exists:servicios,id',
        'fecha_vencimiento' => 'date|after:today',
    ]);
    
    $cuenta = $this->cuentaService->generarCuenta(
        $validated['servicios_ids']
    );
    
    return response()->json([
        'success' => true,
        'data' => $cuenta,
        'message' => 'Cuenta de cobro generada: ' . $cuenta->numero,
    ], 201);
}
```

---

## RN-13/14: Validación y Bloqueo de Agenda para Técnicos

### Descripción
No se puede asignar un servicio a un técnico si:
- Tiene otro servicio en la misma fecha/hora
- Tiene un bloqueo de agenda en esa fecha
- Horas trabajadas superan límite diario (8 horas)

### Implementación

**Backend Service:**
```php
// app/Services/AgendaService.php
public function validarDisponibilidad($tecnicoId, $fechaServicio, $duracionHoras) {
    $tecnico = Tecnico::findOrFail($tecnicoId);
    
    $errores = [];
    
    // 1. Verificar bloqueos
    $bloqueosActivos = BloqueosAgenda::where('tecnico_id', $tecnicoId)
        ->whereDate('fecha_inicio', '<=', $fechaServicio)
        ->whereDate('fecha_fin', '>=', $fechaServicio)
        ->where('activo', 1)
        ->get();
    
    if ($bloqueosActivos->isNotEmpty()) {
        $errores[] = "Técnico tiene bloqueos en esta fecha: " . 
                     $bloqueosActivos->pluck('razon')->implode(', ');
    }
    
    // 2. Verificar conflictos de horario
    $serviciosEnFecha = Servicio::where('tecnico_id', $tecnicoId)
        ->whereDate('fecha_servicio', $fechaServicio)
        ->where('estado', '!=', 'cancelado')
        ->get();
    
    foreach ($serviciosEnFecha as $servicio) {
        if ($this->hayConflictoHorario(
            $fechaServicio,
            $duracionHoras,
            $servicio->fecha_servicio,
            $servicio->duracion_estimada
        )) {
            $errores[] = "Conflicto con servicio {$servicio->numero}";
        }
    }
    
    // 3. Verificar límite diario (8 horas)
    $horasDelDia = Servicio::where('tecnico_id', $tecnicoId)
        ->whereDate('fecha_servicio', $fechaServicio)
        ->where('estado', '!=', 'cancelado')
        ->sum('duracion_estimada');
    
    if (($horasDelDia + $duracionHoras) > 8) {
        $errores[] = sprintf(
            "Excede límite diario: %d + %d > 8 horas",
            $horasDelDia,
            $duracionHoras
        );
    }
    
    return [
        'disponible' => empty($errores),
        'errores' => $errores,
    ];
}

private function hayConflictoHorario($fecha1, $duracion1, $fecha2, $duracion2) {
    $inicio1 = strtotime($fecha1);
    $fin1 = $inicio1 + ($duracion1 * 3600);
    
    $inicio2 = strtotime($fecha2);
    $fin2 = $inicio2 + ($duracion2 * 3600);
    
    return !($fin1 <= $inicio2 || $fin2 <= $inicio1);
}

public function crearBloqueo($tecnicoId, $fechaInicio, $fechaFin, $razon, $tipo) {
    // Validar que no tenga servicios en ese rango
    $servicios = Servicio::where('tecnico_id', $tecnicoId)
        ->whereBetween('fecha_servicio', [$fechaInicio, $fechaFin])
        ->where('estado', '!=', 'cancelado')
        ->get();
    
    if ($servicios->isNotEmpty()) {
        throw new ValidationException(
            "No se puede bloquear: existen servicios programados"
        );
    }
    
    return BloqueosAgenda::create([
        'tecnico_id' => $tecnicoId,
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
        'razon' => $razon,
        'tipo' => $tipo, // vacaciones, mantenimiento, formacion
        'usuario_creador_id' => Auth::id(),
        'activo' => 1,
    ]);
}
```

---

## RN-16: Auditoría de Transiciones de Estado

### Descripción
Todas las transiciones de estado de servicios y cuentas se registran automáticamente con:
- Fecha/hora exacta
- Usuario que realizó el cambio
- Estado anterior y nuevo
- Contexto (IP, User-Agent)

### Implementación

**Backend Service:**
```php
// app/Services/AuditoriaService.php
public static function registrar($accion, $entidad, $entidadId, 
                                  $estadoAnterior = null, 
                                  $estadoNuevo = null,
                                  $detalles = []) {
    $auditoria = Auditoria::create([
        'usuario_id' => Auth::id(),
        'accion' => $accion, // crear, actualizar, eliminar
        'entidad' => $entidad, // Servicio, CuentaCobro
        'entidad_id' => $entidadId,
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo' => $estadoNuevo,
        'detalles' => json_encode($detalles),
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'fecha_hora' => now(),
    ]);
    
    Log::channel('auditoria')->info(
        "[{$accion}] {$entidad} #{$entidadId}",
        [
            'usuario_id' => Auth::id(),
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
        ]
    );
    
    return $auditoria;
}

public static function obtenerHistorial($entidad, $entidadId) {
    return Auditoria::where('entidad', $entidad)
                     ->where('entidad_id', $entidadId)
                     ->orderBy('fecha_hora', 'desc')
                     ->get()
                     ->map(fn($a) => [
                         'fecha' => $a->fecha_hora,
                         'usuario' => $a->usuario->nombre,
                         'accion' => $a->accion,
                         'de' => $a->estado_anterior,
                         'a' => $a->estado_nuevo,
                         'detalles' => json_decode($a->detalles, true),
                     ]);
}
```

**Middleware automático:**
```php
// app/Middleware/LoggingMiddleware.php
public function handle(Request $request, Closure $next) {
    $response = $next($request);
    
    if ($request->isMethod('PUT') || $request->isMethod('POST')) {
        AuditoriaService::registrar(
            $request->isMethod('POST') ? 'crear' : 'actualizar',
            $this->extraerEntidad($request->path()),
            $this->extraerIdDeRequest($request),
            detalles: $request->all()
        );
    }
    
    return $response;
}
```

---

## RN-23: Borrado Lógico (Soft Delete)

### Descripción
Nunca se eliminan registros físicamente. Se utiliza campo `activo` para marcar como inactivo.

### Implementación

**En Models:**
```php
class Usuario extends BaseModel {
    protected $softDelete = true;
    
    public function scopeActivos($query) {
        return $query->where('activo', 1);
    }
}
```

**En Queries:**
```php
// Obtener solo activos (automático)
$usuarios = Usuario::all(); // WHERE activo = 1

// Obtener todos incluyendo eliminados
$todos = Usuario::withDeleted()->get();

// Borrado
Usuario::find($id)->delete(); // SET activo = 0

// Restaurar
Usuario::find($id)->restore(); // SET activo = 1
```

---

## RN-25: Integridad Referencial (ON DELETE RESTRICT)

### Descripción
No se puede eliminar un registro si existen referencias en otras tablas.

### Implementación SQL

```sql
-- Servicios dependen de Técnicos
ALTER TABLE servicios
ADD CONSTRAINT fk_servicios_tecnico
FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id)
ON DELETE RESTRICT
ON UPDATE CASCADE;

-- Cuentas dependen de Servicios
ALTER TABLE cuentas_cobro
ADD CONSTRAINT fk_cuentas_servicios
FOREIGN KEY (servicio_id) REFERENCES servicios(id)
ON DELETE RESTRICT
ON UPDATE CASCADE;

-- Auditoria no debe perderse
ALTER TABLE auditoria
ADD CONSTRAINT fk_auditoria_usuarios
FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
ON DELETE RESTRICT;
```

**Manejo en PHP:**
```php
try {
    $tecnico = Tecnico::findOrFail($id);
    $tecnico->delete();
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
        throw new ValidationException(
            "No se puede eliminar: técnico tiene servicios asignados"
        );
    }
}
```

---

## Tabla de Transiciones de Estado Permitidas

### Servicios
```
Pendiente → Asignado → En Progreso → Completado ✓
Pendiente → Cancelado ✓
Cualquiera → Cancelado (solo Admin) ✓
```

### Cuentas de Cobro
```
Pendiente → Pagada ✓
Pendiente → Vencida (automático por fecha) ✓
Cualquiera → Anulada (borrado lógico) ✓
```

---

## Tabla SESIONES_JWT

```sql
CREATE TABLE sesiones_jwt (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    access_token VARCHAR(2000) NOT NULL,
    refresh_token VARCHAR(2000) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion TIMESTAMP,
    activa TINYINT DEFAULT 1,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
```
