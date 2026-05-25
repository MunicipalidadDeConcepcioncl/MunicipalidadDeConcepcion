# Sitio Cero (Entorno limpio)

Este repositorio usa un entorno independiente para `sitio-cero` en:

- `projects/sitio-cero/`

El entorno tiene su propia configuracion, base de datos y volumenes.

## Estructura relevante

- `projects/sitio-cero/.env`
- `projects/sitio-cero/docker-compose.yml`
- `wp-content/themes/sitio-cero/`

## Variables del entorno sitio-cero

Archivo: `projects/sitio-cero/.env`

- `COMPOSE_PROJECT_NAME=sitio_cero`
- `WP_PORT=8080`
- `MYSQL_DATABASE=wordpress_sitio`
- `MYSQL_USER=wp_sitio`
- `MYSQL_PASSWORD=wp_sitio_pass`
- `MYSQL_ROOT_PASSWORD=root_sitio_pass`

## Levantar entorno

```bash
cd projects/sitio-cero
docker compose up -d
```

Abrir en navegador:

- [http://localhost:8080](http://localhost:8080)

## Limpieza total del entorno sitio-cero

Si quieres dejarlo sin rastros previos (DB + archivos WordPress del volumen):

```bash
cd projects/sitio-cero
docker compose down -v
```

Luego lo levantas de nuevo:

```bash
docker compose up -d
```

## Activar tema

En WordPress:

1. `Apariencia > Temas`
2. Activar `Sitio Cero`

## Deploy a cPanel via GitHub

Este repo ahora soporta dos modos con `scripts/cpanel/deploy.sh`:

- `custom`: despliega solo `theme + mu-plugins` (modo legacy).
- `full`: despliega WordPress completo (archivos + base de datos).

### 1) Configuracion cPanel actual

Configuracion aplicada:

- URL sitio: `demo.lbcchile.com`
- Repositorio cPanel: `Muni`
- Ruta repo en servidor: `/home/ligabasq/repositories/Muni`
- `DEPLOYPATH` actual: `/home/ligabasq/public_html/demo`

Archivo `.cpanel.yml`:

- `DEPLOYPATH=/home/ligabasq/public_html/demo`
- `FULL_DEPLOY_PACKAGE=deploy/full/latest.tar.gz`
- `IMPORT_DB_ON_DEPLOY=0`

Con `FULL_DEPLOY_PACKAGE` definido, cPanel ejecuta modo `full`.

### 2) Preparar paquete full desde Docker local

Genera snapshot del WordPress local en ejecución (`/var/www/html` + SQL):

```bash
bash scripts/cpanel/package-full-deploy.sh
```

Resultado:

- `deploy/full/wordpress-full-YYYYmmdd-HHMMSS.tar.gz`
- `deploy/full/latest.tar.gz`

El deploy full en cPanel usa `deploy/full/latest.tar.gz`.

### 3) Publicar para deploy full

1. Genera el paquete full.
2. Commit y push del repo (incluyendo `deploy/full/latest.tar.gz`).
3. En `cPanel > Git Version Control > Manage`:
   - `Update from Remote`
   - `Deploy HEAD Commit`

### 4) Importar DB en cPanel (opcional)

Por defecto la importacion está desactivada (`IMPORT_DB_ON_DEPLOY=0`).

Si quieres importar SQL durante deploy:

1. Cambia en `.cpanel.yml`: `IMPORT_DB_ON_DEPLOY=1`.
2. Define variables en el entorno del deploy (o antes de ejecutar script):
   - `CPANEL_DB_HOST` (default `localhost`)
   - `CPANEL_DB_PORT` (default `3306`)
   - `CPANEL_DB_NAME`
   - `CPANEL_DB_USER`
   - `CPANEL_DB_PASSWORD`

Si faltan credenciales, el deploy falla de forma segura.

### 5) Volver a modo custom (sin full package)

En `.cpanel.yml`, elimina `FULL_DEPLOY_PACKAGE` o dejalo vacio:

```yaml
- export FULL_DEPLOY_PACKAGE=
```

Con eso el deploy vuelve al modo legacy (solo `theme + mu-plugins`).
