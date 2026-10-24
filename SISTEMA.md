# Identidad del sistema

**Este sistema se llama Gravity.** Es un sistema nuevo e independiente.

## Lo que ES

- **Nombre:** Gravity
- **Repo:** este directorio (`c:\xampp\htdocs\gravity` en dev)
- **Base de datos PostgreSQL:** `gravity` (ver `config/database.php` → `DB_NAME`)
- **Dominio en producción:** `genpharma.cloud` y subdominios
- **Propósito:** POS multi-tenant con mesas/comandas (fase actual: evolución desde el POS original hacia flujo de restaurante / punto de venta general)

## Lo que NO es

- **No es "farmacia".** Existe una base PostgreSQL llamada `farmacia` tanto en el server local como en el VPS. **Pertenece a otro sistema completamente distinto, no se toca.** Cualquier comando `psql`, migración, dump o backup contra `-d farmacia` es un error.
- **No es "Generyc Pharma".** Los schemas `generic_pharma_jr_alonso_de_alvarado` y `generic_pharma_jr_lima_tambo` que existen dentro de la base `gravity` son nombres heredados del copy-paste original. **Generyc Pharma como marca ya no existe en este contexto** — son solo nombres de schema que arrastran historia; conceptualmente son schemas sucursal del sistema Gravity. Si en algún momento se renombran, mejor; hasta entonces, tratarlos como "schema sucursal X del sistema Gravity", nada más.

## Por qué este archivo existe

El código actual es un fork/copia del sistema original "farmacia" y durante la transición algunos nombres (variables, comentarios, documentación interna, schemas) quedaron con la nomenclatura vieja. Esto confunde al leer el código y provocó bugs reales (ej. corré un `psql -d farmacia` creyendo que era la base de Gravity).

**Regla general:** cuando en código/doc viejo diga "farmacia" o "generyc pharma", es herencia obsoleta — este sistema se llama **Gravity** y vive en la base `gravity`.

## Referencia cruzada

- `CLAUDE.md` — guía detallada para Claude Code
- `config/database.php` → `DB_NAME = 'gravity'` (la verdad canónica del nombre de DB)
