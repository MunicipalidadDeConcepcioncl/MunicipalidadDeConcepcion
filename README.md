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

Este repo ya incluye archivos base para deploy desde cPanel:

- `.cpanel.yml`
- `scripts/cpanel/deploy.sh`

### 1) Ajustar ruta de despliegue

Configuracion aplicada para este proyecto:

- URL sitio: `demo.lbcchile.com`
- Repositorio cPanel: `Muni`
- Ruta repo en servidor: `/home/ligabasq/repositories/Muni`
- `DEPLOYPATH` actual: `/home/ligabasq/public_html/demo.lbcchile.com`

### 2) Configurar repositorio en cPanel

En `cPanel > Git Version Control`:

1. Crea o vincula el repositorio desde GitHub.
2. Usa el nombre de repositorio `Muni`.
3. Verifica que el path del repositorio sea `/home/ligabasq/repositories/Muni`.
4. Selecciona la rama que usarás para producción (recomendado `main` o `production`).
5. Abre `Manage` del repo y ejecuta `Update from Remote`.
6. Ejecuta `Deploy HEAD Commit`.

### 3) Qué despliega este proyecto

El script despliega solo contenido custom de WordPress:

- `wp-content/themes/sitio-cero/`
- `wp-content/mu-plugins/`

No toca archivos core de WordPress.
