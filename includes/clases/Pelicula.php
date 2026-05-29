<?php
/**
 * Clase Pelicula
 * Representa una película del sistema y encapsula las consultas
 * relacionadas con el catálogo y la cartelera.
 */
class Pelicula
{
    public int    $id;
    public string $titulo;
    public int    $duracion;   // minutos
    public string $poster;     // ruta de la imagen

    // Propiedades extendidas (no en UML pero necesarias para las vistas)
    public string $genero         = '';
    public string $clasificacion  = '';
    public string $sinopsis       = '';
    public ?float $precioDesde    = null;

    public function __construct(array $fila)
    {
        $this->id            = (int)$fila['id'];
        $this->titulo        = $fila['titulo'] ?? '';
        $this->duracion      = (int)($fila['duracion_min'] ?? 0);
        $this->poster        = $fila['imagen'] ?? '';
        $this->genero        = $fila['genero'] ?? '';
        $this->clasificacion = $fila['clasificacion'] ?? '';
        $this->sinopsis      = $fila['sinopsis'] ?? '';
        $this->precioDesde   = isset($fila['precio_desde']) ? (float)$fila['precio_desde'] : null;
    }

    /**
     * Lista todas las películas activas con funciones próximas.
     *
     * @param  int $limite  Máximo de resultados (0 = sin límite)
     * @return self[]
     */
    public static function ListarPeliculas(int $limite = 0): array
    {
        $pdo = obtenerConexion();
        try {
            $sql = "
                SELECT DISTINCT
                    p.id,
                    p.titulo,
                    p.genero,
                    p.clasificacion,
                    p.duracion_min,
                    p.imagen,
                    p.sinopsis,
                    (
                        SELECT MIN(f2.precio)
                        FROM   funciones f2
                        WHERE  f2.pelicula_id = p.id
                          AND  f2.activa      = 1
                          AND  f2.fecha_hora  >= NOW()
                    ) AS precio_desde
                FROM peliculas p
                INNER JOIN funciones f ON f.pelicula_id = p.id
                WHERE p.activa  = 1
                  AND f.activa  = 1
                  AND f.fecha_hora >= NOW()
                ORDER BY p.fecha_estreno DESC
            ";
            if ($limite > 0) {
                $sql .= " LIMIT :limite";
            }
            $stmt = $pdo->prepare($sql);
            if ($limite > 0) {
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            }
            $stmt->execute();
            return array_map(fn($fila) => new self($fila), $stmt->fetchAll());
        } catch (PDOException $e) {
            registrarError('Pelicula::ListarPeliculas', $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el detalle completo de una película por su ID.
     *
     * @param  int $id
     * @return self|null
     */
    public static function ObtenerDetalle(int $id): ?self
    {
        $pdo = obtenerConexion();
        try {
            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.titulo,
                    p.genero,
                    p.clasificacion,
                    p.duracion_min,
                    p.imagen,
                    p.sinopsis,
                    (
                        SELECT MIN(f.precio)
                        FROM   funciones f
                        WHERE  f.pelicula_id = p.id
                          AND  f.activa      = 1
                          AND  f.fecha_hora  >= NOW()
                    ) AS precio_desde
                FROM peliculas p
                WHERE p.id = :id AND p.activa = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $fila = $stmt->fetch();
            return $fila ? new self($fila) : null;
        } catch (PDOException $e) {
            registrarError('Pelicula::ObtenerDetalle', $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene la cartelera completa: películas activas con sus funciones
     * agrupadas por fecha.
     *
     * @return array  ['pelicula' => Pelicula, 'funciones_por_fecha' => [...]][]
     */
    public static function ObtenerCartelera(): array
    {
        $pdo = obtenerConexion();
        try {
            // Películas activas con funciones futuras
            $stmtPelis = $pdo->prepare("
                SELECT DISTINCT
                    p.id,
                    p.titulo,
                    p.genero,
                    p.clasificacion,
                    p.duracion_min,
                    p.imagen,
                    p.sinopsis,
                    (
                        SELECT MIN(f2.precio)
                        FROM   funciones f2
                        WHERE  f2.pelicula_id = p.id
                          AND  f2.activa      = 1
                          AND  f2.fecha_hora  >= NOW()
                    ) AS precio_desde
                FROM peliculas p
                INNER JOIN funciones f ON f.pelicula_id = p.id
                WHERE p.activa  = 1
                  AND f.activa  = 1
                  AND f.fecha_hora >= NOW()
                ORDER BY p.fecha_estreno DESC
            ");
            $stmtPelis->execute();

            // Funciones de los próximos 7 días
            $stmtFunciones = $pdo->prepare("
                SELECT
                    f.id           AS funcion_id,
                    f.pelicula_id,
                    f.fecha_hora,
                    f.precio,
                    f.idioma,
                    s.nombre       AS sala,
                    s.tipo         AS tipo_sala,
                    (
                        SELECT COUNT(*) FROM asientos a WHERE a.sala_id = f.sala_id
                    ) - (
                        SELECT COUNT(*) FROM reservas r
                        WHERE  r.funcion_id = f.id
                          AND  r.estado IN ('pendiente','confirmada')
                    ) AS asientos_libres
                FROM funciones f
                INNER JOIN salas s ON s.id = f.sala_id
                WHERE f.activa    = 1
                  AND f.fecha_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                ORDER BY f.fecha_hora ASC
            ");
            $stmtFunciones->execute();
            $todasFunciones = $stmtFunciones->fetchAll();

            $resultado = [];
            foreach ($stmtPelis->fetchAll() as $filaPeli) {
                $pelicula = new self($filaPeli);

                // Agrupar funciones de esta película por fecha
                $porFecha = [];
                foreach ($todasFunciones as $f) {
                    if ((int)$f['pelicula_id'] !== $pelicula->id) continue;
                    $fecha = substr($f['fecha_hora'], 0, 10); // YYYY-MM-DD
                    $porFecha[$fecha][] = $f;
                }

                $resultado[] = [
                    'pelicula'           => $pelicula,
                    'funciones_por_fecha' => $porFecha,
                ];
            }

            return $resultado;
        } catch (PDOException $e) {
            registrarError('Pelicula::ObtenerCartelera', $e->getMessage());
            return [];
        }
    }
}
