# Modelado de conocimiento

Nota de diseño iniciada el 20/09/2026 y actualizada el 23/09/2026. Estos acuerdos no autorizan crear
tablas, campos ni migraciones nuevas.

## Tablas

- `knowledge_sources`: material original ingresado al sistema. Cada página web leída es una fuente.
- `knowledge_insights`: conclusiones; nivel 1 derivado de una o más fuentes y nivel 2 derivado de
  conclusiones de nivel 1.

## Campos de `knowledge_insights`

| Campo | Para qué sirve |
| --- | --- |
| `client_id`, `brand_id` | Cliente y marca. |
| `knowledge_source_ids` | JSON con los IDs de las fuentes de las que sale la conclusión, por ejemplo `[41, 43]`. |
| `research_run_id` | Investigación que generó la conclusión. |
| `parent_insight_ids` | JSON con los IDs de las conclusiones de nivel 1 que sostienen una de nivel 2, por ejemplo `[12, 18]`. |
| `status` | `active`, `outdated`, `superseded` o `rejected`. Ver "Estados". |
| `level` | 1 o 2. |
| `type` | Qué clase de conclusión es. Ver "Tipos". |
| `body` | Texto original generado por la IA. No se modifica. |
| `user_body` | Última corrección del usuario, nullable. |
| `payload` | Datos de respaldo, cuando hacen falta. |
| `model` | Modelo de IA que generó la conclusión. |

- En `knowledge_source_ids` y `parent_insight_ids` no hay claves foráneas dentro del JSON; el service
  valida que los IDs pertenezcan a la marca. Se aceptan las consultas al JSON para buscar dependencias,
  dado el volumen esperado inicialmente.
- Se muestra `user_body` cuando tiene valor; en caso contrario, `body`. Se conservan el original y la
  última corrección, no las correcciones intermedias.

## Estados

- `active`: vigente.
- `outdated`: la reemplazó una investigación nueva.
- `superseded`: el usuario la corrigió. Sigue vigente.
- `rejected`: el usuario la rechazó.

Las conclusiones actuales de una marca son las de un tipo con estado `active` o `superseded`.

Cuando una investigación termina bien, las conclusiones `active` anteriores de la marca y del mismo
tipo pasan a `outdated`. Las `superseded` y las `rejected` no se tocan.

## Tipos

Cada tipo puede tener su propio comportamiento. Hoy existen dos, generados por la investigación del
sitio web:

- `website_brand_analysis`: una fila por investigación. `body` es un resumen de la marca y `payload`
  guarda la respuesta completa del análisis, que también completa el perfil de la marca.
- `website_insight`: de 0 a 7 filas por investigación, una por conclusión general: observaciones que
  atraviesan el sitio, tensiones o huecos, y hechos útiles para comunicar. Solo con evidencia en el
  contenido. `payload` queda en `null`.

Ambos apuntan a todas las páginas leídas en la investigación.

## Pendiente

- Conclusiones cargadas a mano por el usuario.
- Diferencia entre borrar y rechazar una conclusión.
- Lote para agrupar procesos en paralelo, cuando llegue la generación de imágenes.
