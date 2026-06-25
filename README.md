# CineFlow

## Ejecutar localmente

1. Inicia Apache y MySQL en XAMPP.
2. Importa la base de datos desde [db/schema.sql](db/schema.sql).
3. Desde la carpeta del proyecto, ejecuta:

```bash
php -S 127.0.0.1:8000 -t .
```

4. Abre http://127.0.0.1:8000 en tu navegador.

Si tu MySQL usa otro puerto, define la variable de entorno `DB_PUERTO` antes de ejecutar el servidor.
