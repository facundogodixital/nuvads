# Modelo de conocimiento

Nota de diseño iniciada el 20/09/2026 y actualizada el 24/09/2026. Estos acuerdos no autorizan crear
tablas, campos ni migraciones nuevas.

Lo que el sistema sabe de una marca vive en tres lugares: las fuentes (el material leído), las
conclusiones (lo que la IA sacó de ese material) y los campos de la marca (el perfil que ve y edita el
usuario). Cómo se generan está en [research-runs.md](research-runs.md).

## Tablas

- `knowledge_sources`: material original ingresado al sistema. Cada página web leída, posteo de
  Instagram, anuncio de Meta o reseña de Google es una fuente.
- `knowledge_insights`: conclusiones; nivel 1 derivado de una o más fuentes y nivel 2 derivado de
  conclusiones de nivel 1.

## Fuentes

| Campo | Para qué sirve |
| --- | --- |
| `client_id`, `brand_id` | Cliente y marca. |
| `type` | Qué clase de fuente es. Ver "Tipos de fuente". |
| `title` | Título para mostrar: el de la página, o el copy del posteo o del anuncio. |
| `source_ref` | Referencia al original, por ejemplo la URL. |
| `payload` | El material leído. Su forma depende del tipo. |
| `s3_path` | Archivo guardado, para las fuentes que lo tengan. Todavía sin uso. |
| `captured_at` | Cuándo se leyó. |
| `status` | `pending`, `ready` o `failed`. |

### Tipos de fuente

- `web_page`: una página del sitio. `payload` guarda `url`, `provider` (`firecrawl`) y `raw_json`, la
  respuesta completa de Firecrawl.
- `instagram_post`: un posteo. `payload` guarda `url`, `caption`, las imágenes enviadas al modelo en
  `image_urls`, lo que el modelo vio en cada una en `images` (`transcription` y `description`) y el
  ítem completo de Apify en `raw`.
- `meta_ad`: un anuncio. `payload` guarda `url` (su enlace en la Biblioteca de anuncios), `copy`,
  `media` (cada imagen o video, con `type`, `image_url`, que en los videos es la portada, y
  `video_url`), `images` como en Instagram, `days_running` al momento del análisis y el ítem
  completo de Apify en `raw`.
- `google_review`: una reseña de Google Maps. `title` es el comienzo del texto, o "5 estrellas, sin texto",
  y `source_ref` el enlace a la reseña. `payload` guarda `url`, `text` (null si solo tiene estrellas),
  `stars`, `published_at`, `likes_count`, `owner_response` (`text` y `date`, o null), `author` (`name`,
  `is_local_guide` y `reviews_count`), `detailed_rating` (puntaje por aspecto, como comida o servicio) y
  `context` (datos como el tipo de servicio o el precio por persona); los dos últimos en null cuando la
  reseña no los tiene. No guarda el ítem de Apify. A diferencia de las otras fuentes, una investigación
  nueva reemplaza las reseñas anteriores de la marca: las borra al terminar bien.

El esquema prevé además `audio`, `whatsapp_export`, `image` y `adjustment`, todavía sin uso.

Las URLs de imágenes y videos de Instagram y de Meta vencen a los pocos días: el análisis las usa
en el momento, y cuando ya no cargan la pantalla muestra el ícono del formato o un aviso.

## Conclusiones

| Campo | Para qué sirve |
| --- | --- |
| `client_id`, `brand_id` | Cliente y marca. |
| `knowledge_source_ids` | JSON con los IDs de las fuentes de las que sale la conclusión, por ejemplo `[41, 43]`. |
| `research_run_id` | Investigación que generó la conclusión. |
| `parent_insight_ids` | JSON con los IDs de las conclusiones de nivel 1 que sostienen una de nivel 2, por ejemplo `[12, 18]`. |
| `status` | `active`, `outdated`, `superseded` o `rejected`. Ver "Estados". |
| `level` | 1 o 2. |
| `type` | Qué clase de conclusión es. Ver "Tipos de conclusión". |
| `body` | Texto original generado por la IA. No se modifica. |
| `user_body` | Última corrección del usuario, nullable. |
| `payload` | Datos de respaldo, cuando hacen falta. |
| `model` | Modelo de IA que generó la conclusión. |

- En `knowledge_source_ids` y `parent_insight_ids` no hay claves foráneas dentro del JSON; el service
  valida que los IDs pertenezcan a la marca. Se aceptan las consultas al JSON para buscar dependencias,
  dado el volumen esperado inicialmente.
- Se muestra `user_body` cuando tiene valor; en caso contrario, `body`. Se conservan el original y la
  última corrección, no las correcciones intermedias.

### Estados

- `active`: vigente.
- `outdated`: la reemplazó una investigación nueva.
- `superseded`: el usuario la corrigió. Sigue vigente.
- `rejected`: el usuario la rechazó.

Las conclusiones actuales de una marca son las de un tipo con estado `active` o `superseded`.

Cuando una investigación termina bien, las conclusiones `active` anteriores de la marca y del mismo
tipo pasan a `outdated`. Las `superseded` y las `rejected` no se tocan.

### Tipos de conclusión

Las investigaciones del sitio web, de Instagram y de anuncios dejan un análisis y de 0 a 7 conclusiones,
todos de nivel 1 y apuntando a las fuentes que leyó. La de reseñas de Google deja más tipos; ver abajo.

- `website_brand_analysis`: una fila por investigación del sitio web. `body` es un resumen de la marca y
  `payload` guarda la respuesta completa del análisis (`brand`, `inferred_fields`, `summary` e
  `insights`) y la identidad visual encontrada en `visual`.
- `instagram_analysis`: una fila por investigación de Instagram. `body` es un resumen de la cuenta y
  `payload` guarda la respuesta del análisis (`brand`, `summary` e `insights`) y las métricas en
  `metrics`: `posts_count`, `posts_per_week` y, por formato, `posts`, `average_likes` y
  `average_comments`.
- `meta_ads_analysis`: una fila por investigación de anuncios. `body` es un resumen de su publicidad y
  `payload` guarda la respuesta del análisis y las métricas en `metrics`: `ads_count`,
  `longest_running_days`, por formato `ads` y `average_days_running`, y en `platforms` la cantidad
  de anuncios por plataforma. Si la página no tiene anuncios, `body` lo dice, `ads_count` es 0 y no
  apunta a ninguna fuente.
- `website_insight`, `instagram_insight` y `meta_ads_insight`: una fila por conclusión: patrones,
  tensiones o huecos, y hechos útiles para comunicar, siempre con evidencia en las fuentes. `payload`
  queda en `null`.

Las reseñas de Google dejan cinco tipos, todos de nivel 1:

- `google_reviews_metrics`: una fila por investigación con las métricas, que calcula PHP: `model` queda
  en null y `body` es un texto fijo. `payload` guarda `google_total_score` y `google_reviews_count` (lo
  que muestra la ficha), `reviews_count`, `with_text_count`, `average_stars`, `stars_distribution`,
  `reviews_per_month` (los últimos 12 meses), `owner_response_rate`, `median_owner_response_days`,
  `detailed_rating_averages`, `oldest_review_at`, `newest_review_at` y `time_ranges`: hasta cuatro
  tramos de tiempo, del más viejo al más nuevo, con la misma cantidad de reseñas con texto cada uno.
  Cada tramo trae `from`, `to`, `reviews_count` y `average_stars`.
- `google_reviews_brand_analysis`: una fila por investigación. `body` es un resumen de lo que dicen los
  clientes y `payload` guarda los campos mezclados en `brand`, `summary` y los datos de apoyo en
  `facts` (datos prácticos), `profiles` (quiénes van), `products` y `staff` (personas del equipo), cada
  uno como lista de temas con `topic`, `knowledge_source_ids` y `mentions_count`.
- `google_reviews_pain` y `google_reviews_strength`: una fila por queja o por elogio. `body` es el tema y
  `knowledge_source_ids`, las reseñas que lo mencionan. `payload` guarda `highlight_ids` (hasta tres
  reseñas para mostrar como referencia), `mentions_count`, `mentions_share` (qué parte de las reseñas
  con texto lo menciona), `range_shares` (qué parte de las reseñas de cada tramo lo menciona, en el orden
  de `time_ranges`), `last_mentioned_at` y `trend`: `resolved` (ya no aparece en el último tramo),
  `emerging` (aparece solo ahí) u `ongoing`. Con un solo tramo, `trend` queda en null.
- `google_reviews_insight`: de 0 a 12 conclusiones que cruzan datos. Apuntan a las reseñas de los temas
  en que se apoyan, o a todas si salen solo de las métricas, y `payload` guarda sus `highlight_ids`.

Todas las filas de una corrida de reseñas apuntan a las reseñas de esa corrida. Cuando una corrida nueva
las reemplaza, las filas anteriores quedan apuntando a reseñas borradas.

## Campos de la marca

Las investigaciones completan el perfil de la marca sin borrar nada:

- Los campos de texto se mezclan: el modelo recibe el texto actual de cada campo, conserva todo lo que
  dice y suma o precisa lo que muestra la fuente. Si la fuente no aporta nada, devuelve el texto
  actual; si el campo está vacío, lo completa solo con evidencia. Lo que el modelo devuelve vacío no
  borra nada.
- El nombre y la identidad visual (logo, colores y fuentes) solo se completan si la marca no los
  tiene, para no pisar lo que eligió el usuario.
- El texto actual se lee justo antes de la consulta final. Si el usuario edita un campo de texto
  mientras el modelo responde, se guarda lo que devuelve el modelo.

Qué campos toca cada investigación:

| Campo | Sitio web | Instagram | Anuncios de Meta | Reseñas de Google |
| --- | --- | --- | --- | --- |
| `name` | Si está vacío | | | |
| `brand_offer_description` | Mezcla | | Mezcla | Mezcla |
| `brand_differentiators_description` | Mezcla | | Mezcla | Mezcla |
| `brand_history_description` | Mezcla | | | |
| `brand_customers_description` | Mezcla | Mezcla | Mezcla | Mezcla |
| `brand_customers_needs_description` | Mezcla | Mezcla | Mezcla | Mezcla |
| `brand_visual_style_description` | Mezcla | Mezcla | Mezcla | |
| `brand_tone_of_voice_description` | Mezcla | Mezcla | Mezcla | Mezcla |
| `brand_customers_valued_aspects_description` | Mezcla | | | Mezcla |
| `brand_customers_faq_description` | Mezcla | | | Mezcla |
| `brand_communication_topics_description` | Mezcla | Mezcla | Mezcla | |
| `brand_content_opportunities_description` | Mezcla | | Mezcla | Mezcla |
| `brand_logos`, `brand_colors`, `brand_fonts` | Si está vacío | | | |

Las pantallas de Instagram, de anuncios y de reseñas muestran qué campos del perfil actualizó su último análisis:
los que el modelo devolvió con texto en `payload.brand`.

## Pendiente

- Conclusiones cargadas a mano por el usuario.
- Diferencia entre borrar y rechazar una conclusión.
- Lote para agrupar procesos en paralelo, cuando llegue la generación de imágenes.
