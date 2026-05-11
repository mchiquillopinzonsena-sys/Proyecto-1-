-- Idempotencia RN-02 / RN-06 al reintentar completar servicio
ALTER TABLE servicios
    ADD COLUMN stock_descuento_aplicado TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 si RN-02 ya descontó inventario para este servicio'
        AFTER activo,
    ADD COLUMN cuenta_cobro_generada TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 si RN-06 ya generó cuenta asociada'
        AFTER stock_descuento_aplicado;
