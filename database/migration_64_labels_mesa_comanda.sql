-- Migration 64: labels configurables de "Mesa" y "Comanda" por tenant.
--
-- Permite que un tenant restaurante use "Mesa"/"Comanda", mientras que un
-- tenant peluqueria use "Estacion"/"Servicio" y un tenant spa use
-- "Cabina"/"Sesion" (o lo que configure cada uno). Se guarda singular +
-- plural por separado para evitar pluralizaciones incorrectas.
--
-- La columna existe a nivel tenant en public.tenant_config; las tablas DB
-- (mesas, comandas) y los nombres de columnas (mesa_id, comanda_id) NO
-- cambian -- esto es solo un relabel de UI/UX.

ALTER TABLE public.tenant_config
    ADD COLUMN IF NOT EXISTS label_mesa_singular    VARCHAR(32) NOT NULL DEFAULT 'Mesa',
    ADD COLUMN IF NOT EXISTS label_mesa_plural      VARCHAR(32) NOT NULL DEFAULT 'Mesas',
    ADD COLUMN IF NOT EXISTS label_comanda_singular VARCHAR(32) NOT NULL DEFAULT 'Comanda',
    ADD COLUMN IF NOT EXISTS label_comanda_plural   VARCHAR(32) NOT NULL DEFAULT 'Comandas';
