-- RN-02: vínculo normalizado servicio_item -> stock (consumo de inventario)
ALTER TABLE servicios_items
    ADD COLUMN stock_id INT NULL COMMENT 'Artículo de inventario consumido en la línea' AFTER servicio_id,
    ADD INDEX idx_stock_id (stock_id),
    ADD CONSTRAINT fk_servicios_items_stock
        FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT ON UPDATE CASCADE;
