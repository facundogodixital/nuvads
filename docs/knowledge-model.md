# Modelado de conocimiento · borrador

Nota de diseño iniciada el 20/09/2026 y actualizada el 21/09/2026. El esquema completo todavía no está aprobado.
Estos acuerdos no autorizan crear tablas, campos ni migraciones.

## Base en discusión

- `knowledge_sources`: material original ingresado al sistema.
- `knowledge_insights`: conclusiones; nivel 1 derivado de una fuente y nivel 2 derivado de conclusiones de nivel 1.
- Los demás campos y comportamientos de la propuesta inicial siguen sujetos a revisión.

## Punto 1 cerrado: trazabilidad de conclusiones de nivel 2

- Usar `parent_insight_ids`, JSON nullable, en `knowledge_insights`.
- Contiene los IDs de los insights de nivel 1 que sostienen una conclusión de nivel 2, por ejemplo `[12, 18]`.
- Para nivel 1, su valor es `null`; la fuente se referencia mediante `knowledge_source_id`.
- Para nivel 2, `knowledge_source_id` es `null`.
- No agregar una tabla de relaciones. Se aceptan la ausencia de claves foráneas dentro del JSON y las consultas al JSON para buscar dependencias, dado el volumen esperado inicialmente.

## Punto 2 cerrado: correcciones del usuario

- `body` conserva el texto original generado por la IA.
- `user_body`, nullable, guarda la última corrección del usuario.
- Se utiliza `user_body` cuando tiene valor; en caso contrario, `body`.
- Conservar `is_user_edited` como indicador explícito para identificar visualmente la intervención del usuario, aunque sea redundante con `user_body`.
- La aplicación debe mantener `user_body` e `is_user_edited` sincronizados.
- Esta estructura conserva el original y la última corrección, no las correcciones intermedias.
- No agregar `supersedes_insight_id` para resolver estas correcciones.

| id | body | user_body | is_user_edited |
|---|---|---|---|
| 12 | La marca vende zapatillas | La marca vende solo indumentaria | true |

## Otros cambios acordados

- Quitar `competitor_id` de ambas tablas por ahora. El modelado de competencia queda para más adelante.
- El índice propuesto de fuentes `(brand_id, competitor_id, type)` pasa a `(brand_id, type)`.
- Renombrar `source_id` a `knowledge_source_id` para identificar claramente la tabla referenciada.
- El índice propuesto de conclusiones `(source_id, status)` pasa a `(knowledge_source_id, status)`.

## Aclaraciones sobre campos propuestos

Estas aclaraciones explican la propuesta; no implican que el resto del esquema esté aprobado.

### `payload`

- En `knowledge_sources`, describe el material, sin analizarlo. Por ejemplo, `{"duration_seconds": 90, "language": "es"}` para un audio en S3 o `{"message_count": 850, "from_date": "2026-08-01", "to_date": "2026-08-31"}` para un export de WhatsApp.
- En `knowledge_insights`, guarda datos numéricos de respaldo cuando hacen falta. Por ejemplo, `{"mentions": 18, "reviews_analyzed": 50}` para una conclusión sobre menciones al asesoramiento en reseñas. En otros casos puede ser `null`.
- El ejemplo del repaso que guardaba el texto de una reseña en `payload` fue corregido: la propuesta original define este campo como metadata. Si se mantiene esa definición, el texto original se guarda en S3.

### `prompt_version`

- Es una etiqueta que identifica las instrucciones usadas para generar una conclusión, no el texto completo del prompt.
- Ejemplo: `source-analysis-v1` extrae fortalezas del material; `source-analysis-v2` además distingue afirmaciones del dueño de las confirmadas por clientes.
- Permite distinguir resultados de distintas instrucciones, incluso usando el mismo modelo.
- Para que la etiqueta sea útil, debe conservarse qué instrucciones corresponden a cada versión. Todavía no se decidió dónde ni cómo.

## Pendiente de debatir

El siguiente punto es cómo representar los resultados de volver a analizar una fuente y su relación con las conclusiones existentes. No está decidido si se actualizan filas o se generan nuevas, ni cómo se preservan las correcciones en ese proceso.
