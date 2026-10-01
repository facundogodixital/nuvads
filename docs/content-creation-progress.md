# Creación de contenido: avance de la implementación

Tablero de la implementación, iniciado el 01/10/2026. El diseño está en [content-creation.md](content-creation.md) y
el objetivo en [objetivo.md](objetivo.md); este archivo no los repite. Acá va qué tramo se está haciendo, qué se le
encargó y qué salió en el camino.

Cómo se trabaja: cada tramo se encarga a un subagente con Opus, sobre `master`, con un encargo aprobado antes por
el usuario. Ante cualquier duda, el subagente frena y la devuelve; se resuelve con el usuario y sigue. Al terminar,
se revisa contra el diseño. Los commits se hacen cuando el usuario los pide.

## Para retomar

Esta sección es lo primero que hay que leer al volver al trabajo, por ejemplo después de compactar la conversación o
al abrir una sesión nueva. Resume el estado, lo que sigue, lo que está abierto y cómo se trabaja. El detalle de cada
tramo está más abajo. Actualizada el 01/10/2026.

### Dónde estamos

- Tramo 1, `content_types` y las tarjetas de tipos: commiteado y pusheado en `be7d3ea`.
- Tramo 2, el paso 2 para Reseñas de clientes: hecho, revisado y commiteado el 01/10/2026, en el commit que sigue a
  `be7d3ea`. No se pushea hasta que el usuario lo pida.
- El usuario ya corrió las dos migraciones y el seeder en la base local, y probó el paso 2 con Up!: generó ideas con
  una llamada real y guardó una. La tabla `ideas` tiene una fila, de Up!, del tipo Reseñas de clientes. No reportó
  problemas, y el log no tiene errores de la generación. Esta sesión nunca vio la pantalla en un navegador.
- Base local: Up! es la marca 1 y tiene 620 reseñas de Google; Clienty es la marca 2 y no tiene ninguna, así que ve
  la tarjeta de Reseñas apagada. Los dos tipos cargados ya tienen sus entradas nuevas.

### Qué sigue

- Escuchar qué le pareció al usuario la prueba con Up!: si las ideas salen buenas es lo que decide los ajustes. La
  receta de Reseñas todavía no dice qué hacer con el puntaje de Google.
- Tramo 3, Educativo de punta a punta: sus siete entradas (`whatsapp_questions`, `audio_insights`, `brand_faq`,
  `audio_transcripts`, `uploaded_documents`, `website_pages`, `whatsapp_purposes`) y su paso 2. Antes de programarlo
  hay que cerrar con el usuario, de a una: qué lee y cuánto manda cada entrada; cómo se generaliza el prompt y la
  validación, que hoy hablan solo de reseñas (`review_ids`, de una a tres reseñas por idea); cómo se muestra en la
  pantalla el respaldo de una idea que no son reseñas; y el ajuste de la receta de Educativo a sus entradas.
- La rotación de tipos ya hace falta en cuanto se cambie la receta de Reseñas, porque hay una idea que apunta a ese
  tipo. No está programada. El seeder nunca actualiza filas que existen, y `deleted_at_ts` guarda segundos. Mientras
  no exista, un cambio de textos en la base local se hace con un `UPDATE` que el agente entrega y el usuario corre.
- Después: el paso 3 (formato, guión y copy), y `styles` con el paso 4 (`pieces`, `piece_images` y dibujar).

### Abierto, sin apuro

- Un texto de la pantalla: si el usuario guarda la última idea sugerida de la lista y vuelve a entrar, dice que no
  salieron ideas, que no es exacto.
- Los demás estados de la idea, además de `chosen`.
- El log de cada generación, con el prompt y la respuesta: el usuario dijo que por ahora no.
- La excepción del skill de capas vale para crear un modelo. No nombra los `update`, que en todo el proyecto reciben
  arrays, ni los arrays de cuatro claves que las entradas arman para el prompt.
- El texto "Tus reseñas de Google todavía no alcanzan" sale de la primera entrada del tipo. Sería inexacto en un tipo
  que empezara por una entrada de grupo.
- Saber si un tipo tiene material arma el material completo de una entrada en cada visita a Crear. Es para optimizar.
- Para el refactor visual que el usuario tiene previsto: el ancho fijo `max-w-[1296px]` repetido en cuatro páginas, y
  `formatDate` y las estrellas repetidos entre Crear y Mi marca.
- El mensaje general de validación del handler dice "Revisá los campos indicados.", en voseo, y la app habla de tú.
- `CreateIdeaRequest` no tiene `messages()`, y el orden de líneas por longitud no se aplicó al armado de los tests:
  el usuario dijo de obviar las dos cosas por ahora.
- En `docs/pendientes.md`, el test anulado de `KnowledgePersistenceTest`. En `docs/pendientes-prod.md`, el tiempo de
  espera del servidor web de producción.

### Cómo se trabaja con el usuario

- El diseño va de a un paso, y no se sigue hasta que da el ok. Cada paso se muestra con lo que ve el usuario en la
  pantalla y con las filas de la base en JSON, con nombres reales de tablas y columnas. Se traen propuestas, no
  listas de preguntas; cuando hay que preguntar, las preguntas van numeradas para que conteste en orden.
- Nada entra a `content-creation.md` sin que el usuario lo haya dicho o aprobado. Lo que el agente propone y él no
  confirmó no se anota como acordado. Este tablero sí lo mantiene el agente.
- Tablas, campos y parámetros de infraestructura necesitan su ok explícito. Las migraciones y los seeders los corre
  él. Un cambio sobre datos ya cargados se le entrega como SQL.
- Implementación de un tramo: el agente redacta el encargo y se lo muestra; con su ok, lo ejecuta un subagente con
  Opus sobre `master`. El backend y el frontend pueden ir en dos corridas en paralelo si no tocan los mismos
  archivos; el frontend recibe el contrato de los endpoints. Después esta sesión corre tests y linters, lee el
  resultado contra el diseño y resuelve con las reglas escritas lo que puede; lo que no, queda como duda para él.
- El subagente lee `AGENTS.md`, `PRODUCT.md`, `DESIGN.md`, el objetivo, el diseño, este tablero y los skills que
  apliquen (`capas-backend`, `api-backend`, `testing-backend`, `frontend-vue`, `jobs-backend`). No corre migraciones
  ni seeders en la base local, no hace llamadas reales ni pagas, no commitea, no toca `docs/` y devuelve sus dudas.
  Para seguir con un subagente que ya trabajó, se lo retoma con un mensaje, así conserva su contexto.
- `revisor-nuvads` se lanza solo cuando el usuario lo pide, con Sonnet. En el tramo 2 lo pidió para cada corrida y
  para el commit anterior. Hay que pasarle el alcance y las excepciones que el usuario aprobó. Lo que encuentra lo
  corrige el subagente; las dudas que marca se le llevan al usuario con el archivo y la línea donde mirar.
- Nada de corridas reales ni llamadas pagas por cuenta propia: la primera generación real la disparó el usuario.
- Los commits se hacen cuando los pide: en inglés entero, sin palabras en castellano como "tramo", con el formato
  `[Main topic] Description`, verbos en pasado y sin líneas de coautoría. El push, solo si lo pide.
- Respuestas cortas y en castellano. Cuando no entiende algo, se le explica con un ejemplo concreto y, si es de
  código, con el enlace al archivo y la línea.

### Convenciones que se aclararon en esta sesión

- Los textos que ve el usuario van en español neutro, de tú. Los prompts para el modelo van en voseo. El contenido de
  una marca va en la voz de esa marca.
- Una palabra por cosa. Un modelo se nombra con el nombre del modelo: un `Idea` sin guardar es una idea sugerida,
  `suggestedIdea`, no una propuesta.
- Un tipo de `KnowledgeSource` no es una entidad: no lleva métodos con su nombre en el service ni en el repository
  del modelo. Las entradas del cerebro usan los métodos genéricos, `findByIds` y `findByTypes`.
- Las entradas no comparten clase base ni herencia: cada una cuenta su historia completa, y la repetición entre
  ellas es deliberada.
- Legibilidad: una línea que junta varias operaciones se parte en variables con nombre, en cada lugar. No se extrae
  una función compartida para lógica chica que se repite: cada componente tiene la suya.
- Los atributos para crear un modelo pueden ir en un array; un DTO va cuando hay que armarlos desde otra estructura.
  Está escrito en el skill `capas-backend`.
- En la API: las comprobaciones de un `POST` van en `after()` del request y fallan como errores de campo; un ID de
  ruta que no existe usa `ModelNotFoundException`; un error del proveedor en un endpoint se relanza como
  `ApiException` con mensaje propio y la original como `previous`; lo que se descarta o se corrige se registra con
  `report()`.
- En los tests: uno por comportamiento, con el presupuesto del skill; el 401 lo cubre `SessionTest`; los comentarios
  son de una o dos líneas. Los subagentes comprueban sus tests rompiendo el código a propósito y restaurándolo.
- Los `layouts` de un tipo describen cómo se ven los elementos dentro de una placa, nunca cómo se reparte el
  contenido entre placas. La regla completa está en el diseño.

### Datos técnicos útiles

- Tests del tramo: `make test ARGS="tests/Feature/ContentCreation"`. Suite completa: `make test`. Linters: `make lint`.
- Build de prueba del frontend, sin tocar `public/build`, dentro del contenedor de Node:
  `npx vite build --outDir /tmp/nuvads-build-check --emptyOutDir`, y después se borra esa carpeta.
- `artisan tinker --execute` no imprime la salida en este entorno, por un aviso de psysh. Para leer la base local se
  usa `php -r` cargando `vendor/autoload.php` y `bootstrap/app.php`.
- Los textos del seeder tienen que ser idénticos a los ejemplos JSON del diseño. Se comprueba con un script que junta
  los literales del seeder y los compara con esos JSON.
- El modelo que genera las ideas es `gpt-6-luna`, en `config/content.php`. Las llamadas parecidas tardan entre 11 y
  18 segundos. `OpenAIHelper` espera hasta 120 segundos y nginx hasta 130, en `docker/nginx/default.conf`.
- El caché de la app es de archivos y la cola es la base de datos. La generación de ideas no usa ninguno de los dos.

## Tramos

Lista propuesta, a ajustar. Cada tramo arranca con el ok del usuario.

| Tramo | Qué incluye | Estado |
| --- | --- | --- |
| 1 | `content_types`: tabla, modelo, repository, service, la carga de dos tipos, el endpoint que los lista y la pantalla del paso 1 con las tarjetas. | Hecho y commiteado |
| 2 | Paso 2 para Reseñas de clientes, de punta a punta: la tabla `ideas`, las cinco entradas de Google, tarjetas prendidas o apagadas, generar ideas y elegir una. | Hecho, revisado, probado por el usuario y commiteado |
| 3 | Las entradas de Educativo y su paso 2. | Pendiente |
| 4 | Paso 3: escribir formato, guión y copy. | Pendiente |
| 5 | `styles`, y paso 4: `pieces`, `piece_images` y dibujar. | Pendiente |

El orden cambió el 01/10/2026: primero un tipo de punta a punta, para ver si el método da ideas buenas antes de
construir todas las entradas. Los estilos pasan al final, porque nada los usa hasta que se dibuja.

## Tramo 1: `content_types`

Estado: hecho por un subagente con Opus sobre `master`, revisado y commiteado el 01/10/2026, en `be7d3ea`. El
usuario ya corrió la migración y el seeder. Sobre la pantalla dijo que lo visual no es importante por ahora, porque
se va a refactorizar. `revisor-nuvads` pasó sobre el commit a pedido del usuario; su resultado está en el tramo 2.

### Alcance

El usuario pidió que este tramo incluya la pantalla, para que ya se vea algo.

- La migración de `content_types` con los campos aprobados y `unique(key, deleted_at_ts)`.
- El modelo `ContentType`, `ContentTypeRepository` y `ContentTypeService`, con tres operaciones: listar los tipos
  activos, buscar el tipo activo de una `key` y crear un tipo.
- `ContentTypeSeeder` con los dos tipos, que crea cada uno solo si no hay una fila activa con esa `key`.
- `GET /api/content-types`: lista los tipos activos y devuelve solo lo que la tarjeta muestra. `instructions`,
  `inputs`, `angles` y `layouts` no salen al frontend.
- La pantalla del paso 1 en `CreatePage`, que ya existe vacía en `/create`: la pregunta y una tarjeta por tipo.
- Los tests del backend.

Fuera de este tramo: las entradas del cerebro, prender y apagar tarjetas, las ideas, los estilos y la rotación.

### Decisiones del usuario (01/10/2026)

- La tabla queda aprobada con los campos de "Tabla aprobada".
- `content_types` no lleva `client_id` ni `brand_id`: es nuestra e igual para todas las marcas. Es una excepción
  explícita a la regla de que toda tabla los lleva. `styles` se decide en su tramo.
- Los dos tipos que se cargan: Reseñas de clientes (`customer_reviews`), sin ángulos, y Educativo (`educational`),
  con ángulos.
- Los `layouts` se escriben con la regla que está en el diseño: cómo se ven y se ordenan los elementos dentro de una
  placa, nunca cómo se reparte el contenido entre placas. El usuario dejó la redacción a criterio del agente; quedaron
  reescritos en el diseño los de los tres tipos de ejemplo.
- La carga es un seeder, `ContentTypeSeeder`, que crea cada tipo si no hay una fila activa con esa `key`.
- La migración y el seeder los corre el usuario.
- Los textos que ve el usuario van en español neutro, de tú: "¿De qué quieres hablar?" y "Lo que ya dijeron de ti".
  Las `instructions`, los `angles` y los `layouts` quedan en voseo, porque son instrucciones para el modelo.
- En este tramo todas las tarjetas salen prendidas y el click no lleva a ningún lado: prender y apagar depende de las
  entradas (tramo 3) y el click, del paso 2 (tramo 4).

### Entradas sumadas después del tramo (01/10/2026)

Con el tramo ya hecho, el usuario aprobó ampliar las `inputs` de los dos tipos. Como las clases de las entradas
todavía no existen, el cambio es solo la lista de nombres, en el seeder y en el diseño.

- Reseñas de clientes suma `google_review_products`, `google_review_staff` y `google_review_score`.
- Educativo suma `audio_transcripts`, `uploaded_documents`, `website_pages` y `whatsapp_purposes`.
- No se sumaron las frases de clientes de WhatsApp como reseñas: son mensajes privados.

El seeder nunca actualiza filas que ya existen, así que las dos filas cargadas en la base local se corrigen con un
`UPDATE` que el agente entrega y el usuario corre. No hace falta rotar: todavía no hay ideas apuntando a esos tipos.
Las `instructions` de los dos tipos no se tocaron; ajustarlas a las entradas nuevas quedó en "Por definir" del diseño.

El tramo 3 tiene entonces doce entradas para construir: las cinco que ya estaban en estos dos tipos y las siete
nuevas. `whatsapp_objections` se suma cuando se cargue Dudas antes de comprar.

### Rotación: fuera de este tramo

El agente la había propuesto dentro del tramo y el usuario no vio cuándo haría falta, así que no se construye ahora.
Rotar es la forma de cambiar un tipo sin perder con qué receta se hizo cada idea, y hace falta recién la primera vez
que se quiera cambiar un tipo que ya tenga ideas apuntándole, o sea después del tramo 4. Hasta entonces el código no
edita ni borra tipos, y `deleted_at_ts` queda en 0. La columna y la clave única siguen en la tabla, como se
aprobaron, para no tener que alterarla ese día.

### Tabla aprobada

```text
id
key            string
name           string
description    string
instructions   text
inputs         json
angles         json, nullable
layouts        json, nullable
created_at, updated_at, deleted_at
deleted_at_ts  unsigned big integer, default 0
unique(key, deleted_at_ts)
```

### Encargo

El texto que recibió el subagente:

````text
Tarea: implementar el tramo 1 de "Creación de contenido" en el repo `/var/www/html/nuvads` (Laravel y Vue, sin TypeScript ni Inertia). Trabajás sobre `master`, en este mismo directorio. Sos un subagente: no podés hablar con el usuario. Todo lo que haya que preguntarle me lo devolvés a mí en tu informe.

Antes de escribir nada, leé completos:

- `AGENTS.md`: los acuerdos del proyecto. Son obligatorios y un revisor los controla línea por línea.
- `PRODUCT.md` y `docs/objetivo.md`: qué es el producto.
- `docs/content-creation.md`: solo "Modelo tentativo de `content_types`", "Ejemplos de carga de `content_types`" y el paso 1 de "Qué ve el usuario en Crear". El resto no es de este tramo y el apéndice está deprecado.
- `docs/content-creation-progress.md`: la sección "Tramo 1".
- `DESIGN.md`: antes de hacer la pantalla.

Antes de la parte que cubre cada uno, leé completo el skill del proyecto que corresponde: `.claude/skills/capas-backend/SKILL.md`, `.claude/skills/api-backend/SKILL.md`, `.claude/skills/testing-backend/SKILL.md` y `.claude/skills/frontend-vue/SKILL.md`. Mirá además cómo está hecho lo equivalente que ya existe, por ejemplo competidores (migración, modelo, repository, service, controller, resource, ruta, service de JS y página), y seguí ese patrón.

Qué construir:

1. Migración `create_content_types_table`. La tabla está autorizada por el usuario (01/10/2026) con exactamente estas columnas, en este orden: `id`; `key` string; `name` string; `description` string; `instructions` text; `inputs` json; `angles` json nullable; `layouts` json nullable; timestamps; soft deletes; `deleted_at_ts` unsigned big integer con default 0. Índice único sobre (`key`, `deleted_at_ts`). No lleva `client_id` ni `brand_id`: es una excepción explícita del usuario, porque la tabla es global. No agregues ninguna otra columna, índice ni tabla.
2. Modelo `ContentType`, con el orden y la configuración que pide la sección 5 de `AGENTS.md`.
3. `ContentTypeRepository` y `ContentTypeService`, según el skill `capas-backend`. Operaciones del service en este tramo: listar los tipos activos ordenados por `id`, buscar el tipo activo de una `key` y crear un tipo. Nada más: no hay actualizar, borrar ni rotar en este tramo, y `deleted_at_ts` queda siempre en 0.
4. `ContentTypeSeeder`, registrado en `DatabaseSeeder`. Carga dos tipos, `customer_reviews` y `educational`, a través del service. Es idempotente: crea cada tipo solo si no existe un tipo activo con esa `key`; nunca actualiza ni borra. Los valores de `instructions`, `inputs`, `angles` y `layouts` son exactamente los de "Ejemplos de carga de `content_types`" de `docs/content-creation.md`, carácter por carácter. El valor guardado de cada texto es una sola línea, sin saltos; partí los literales en el código como haga falta para respetar los 120 caracteres. `name` y `description`: "Reseñas de clientes" con "Lo que ya dijeron de ti", y "Educativo" con "Lo que tus clientes necesitan saber".
5. Endpoint `GET /api/content-types`, dentro del grupo autenticado que ya existe en `routes/api.php`, según el skill `api-backend`. Devuelve los tipos activos y de cada uno solo `id`, `key`, `name` y `description`. `instructions`, `inputs`, `angles`, `layouts` y `deleted_at_ts` no salen nunca al frontend.
6. Pantalla del paso 1 en `resources/js/pages/CreatePage/`, que ya existe vacía y enrutada en `/create`, según el skill `frontend-vue` y `DESIGN.md`, con modo claro y oscuro. Muestra la pregunta "¿De qué quieres hablar?" y una tarjeta por tipo, con su `name` y su `description`, todas a la vista. En este tramo todas las tarjetas se ven prendidas y el click todavía no hace nada: no agregues navegación, rutas nuevas, mensajes de "próximamente" ni lógica de prendido y apagado. Resolvé los estados de carga, de error y de lista vacía como lo hacen las páginas existentes. Los textos que ve el usuario van en español neutro, de tú, como el resto de la app.
7. Tests del backend según el skill `testing-backend`: el endpoint (exige sesión, lista solo los activos, expone solo los cuatro campos), el service, el seeder (carga los dos tipos, y correrlo dos veces no duplica) y la clave única (dos filas activas con la misma `key` fallan).

Límites:

- No ejecutes la migración ni el seeder sobre la base local, ni `migrate:fresh`, ni nada que modifique esa base: los corre el usuario. Los tests usan su propia base, según el skill.
- Corré los tests y los linters del proyecto y dejá pasando todo lo tuyo. No reformatees archivos ajenos a este tramo. Si el entorno Docker no está levantado o falta el entorno de tests, no levantes ni cambies infraestructura más allá de lo que indica el skill de testing: informalo.
- No hagas commits ni operaciones de git que cambien el estado (stash, checkout, reset). Hay cambios sin commitear en `docs/` que no son tuyos. No modifiques nada en `docs/`.
- Fuera de los archivos nuevos, tocá solo los registros que el patrón exige, como `routes/api.php`, `DatabaseSeeder` y el registro scoped del repository y el service.
- No agregues dependencias, librerías ni herramientas. No lances otros agentes ni revisores. No hagas llamadas pagas a servicios externos.
- No construyas nada de los tramos siguientes: entradas del cerebro, estilos, ideas, piezas.
- Ante cualquier duda de alcance, de requisitos o de implementación que los acuerdos y el patrón existente no resuelvan, no la resuelvas con un supuesto: frená esa parte, terminá lo que no depende de ella y devolveme la duda.

Informe final, breve y en castellano: (a) archivos creados y modificados, con una línea por cada uno; (b) todos los textos visibles para el usuario que escribiste, literales; (c) los comandos que corriste y su resultado real, con la salida si algo falla; (d) lo que no hiciste y por qué; (e) dudas para el usuario. No declares terminado nada que no hayas verificado.
````

### Lo que salió en el camino

Qué entregó el subagente:

- La migración `2026_10_01_000001_create_content_types_table`, el modelo `ContentType`, `ContentTypeRepository`,
  `ContentTypeService` (`create`, `list`, `findOneByKey`), `ContentTypeController::list`, `ContentTypeSeeder`
  registrado en `DatabaseSeeder`, la ruta `GET /api/content-types`, `ContentTypeService.js`, la pantalla en
  `CreatePage.vue` y `tests/Feature/ContentCreation/ContentTypesTest.php` con cuatro tests.
- No usa una clase Resource: el modelo oculta con `$hidden` lo que no sale al frontend, como hacen `User` y
  `Administrator`, y el controller responde el modelo, que es el patrón del skill `api-backend`.
- Reformateó `DatabaseSeeder`, que traía el formato de Laravel, al de `AGENTS.md`.

Qué se verificó al revisar:

- Los textos del seeder son idénticos a los del diseño: 19 textos comparados, ninguna diferencia, sin saltos de línea.
- La migración tiene las columnas aprobadas, en el orden aprobado, y la clave única.
- Los cuatro tests del tramo pasan, y Pint, PHPCS y ESLint pasan.
- En la base local la única migración pendiente es la nueva.
- La pantalla tiene la pregunta, una tarjeta por tipo sin acción, y los estados de carga, de error y de lista vacía.
  Nadie la vio todavía en el navegador: falta la tabla en la base local.

Textos visibles que escribió el subagente, aprobados por el usuario: "Cargando los tipos de contenido…", "Volver a
intentar" y "Todavía no hay tipos de contenido para elegir."

Pendientes que deja el tramo:

- Un test que ya fallaba antes y no es de este tramo: `KnowledgePersistenceTest::isolates_reads_and_soft_deletes`,
  en el caso de las fuentes. Borra una fuente `audio`, y desde el commit 8c89a11 solo se pueden borrar fotos y
  documentos. Por decisión del usuario quedó anulado por ahora: ese caso se saltea con `markTestSkipped` y el de las
  conclusiones sigue corriendo. Falta adaptarlo para que borre una fuente que sí se puede borrar. Con eso la suite
  completa pasa, con un test salteado.
- Para cuando se construya la rotación: `deleted_at_ts` guarda segundos, así que rotar dos veces la misma `key` en el
  mismo segundo chocaría con la clave única.
- Documentación, hecha con el ok del usuario: el diseño dice que `content_types` y la primera parte de la pantalla
  ya están programados, y el README suma `migrate` y `db:seed` a la instalación. Además, a pedido del usuario, se
  quitó del diseño el bloque del enfoque anterior, deprecado; sigue en el commit 452c9db.

## Tramo 2: Reseñas de clientes, paso 2 de punta a punta

Estado: hecho, revisado y commiteado el 01/10/2026. El usuario aprobó las decisiones y lo dejó andando mientras no
estaba. Después corrió la migración de `ideas`, lo probó con Up! y guardó una idea.

### Qué es

La tarjeta de Reseñas de clientes se prende o se apaga según el material de la marca, al tocarla la app genera ideas
con las reseñas reales, y el usuario elige una. Se hace con un solo tipo para ver si el método da ideas buenas antes
de construir las demás entradas.

### Decisiones del usuario (01/10/2026)

- La tabla `ideas` queda aprobada con los campos de "Tabla aprobada". `status` es un string abierto; cuando esté
  todo cerrado puede pasar a enum. Hoy su único valor es `chosen`.
- Qué manda cada entrada de Google quedó a criterio del agente, con el pedido de que sea práctico y no quede corto.
  Está escrito en el diseño, en "Qué manda cada entrada de Google".
- Una tarjeta se prende cuando al menos una de sus entradas tiene material. Apagada, dice qué falta. Educativo queda
  apagado con "Disponible pronto" hasta que existan sus entradas.
- Las ideas se generan sin job: la pantalla pide y espera. No hay cola nueva ni caché. El método del controller sube
  su límite de tiempo imitando el `SystemHelper` de Clienty. El modelo es `gpt-6-luna`, el de las investigaciones.
- El prompt lleva la receta del tipo, el material de las entradas y, de fondo, los campos de texto de la marca.
- La pantalla tiene que ser práctica; lo visual no importa por ahora, se va a refactorizar.
- Las clases de las entradas van en `app/Services/ContentTypeInputs/`, con el mismo contrato, y un mapa de nombre a
  clase en la configuración.
- Sin commit: queda para que el usuario lo vea al volver.
- El agente lee los skills de backend, API, tests y frontend, y `revisor-nuvads` pasa después de cada corrida.

### Tabla aprobada

```text
id
client_id, brand_id, content_type_id   claves foráneas
title                  string
angle                  string, nullable
knowledge_insight_ids  json
knowledge_source_ids   json
status                 string
model                  string
created_at, updated_at, deleted_at
```

### Cómo se ejecuta

Dos corridas del subagente con Opus: primero todo el backend, después el frontend, que se hace con los contratos de
los endpoints que deja la primera. Después de cada una, esta sesión corre tests y linters, la revisa contra el diseño
y le pasa `revisor-nuvads` con Sonnet; lo que el revisor encuentra lo corrige el subagente. Una duda que las
decisiones de arriba no cubren espera al usuario. Nadie corre la migración en la base local ni hace llamadas reales
al modelo: los tests las simulan.

Al volver, al usuario le toca correr la migración de `ideas` y probarlo con una marca real. Esa primera generación es
la que cuesta plata, y la dispara él desde la pantalla.

### Tiempo de espera del pedido

El servidor web cortaba un pedido a los 60 segundos, porque `docker/nginx/default.conf` no definía
`fastcgi_read_timeout`, y subir el límite de PHP no cambia ese corte. El usuario aprobó sumar la línea el 01/10/2026.

- En nginx el tiempo quedó global, para todos los pedidos a PHP: `fastcgi_read_timeout 130s`. Hacerlo por endpoint
  en nginx pide un bloque `location` aparte por cada ruta lenta, repitiendo la configuración de PHP.
- El límite por endpoint lo pone PHP, con `SystemHelper::setTimeLimit()` en el método del controller que lo necesita,
  como en Clienty. Generar ideas pide 120 segundos; nginx espera 10 más para que el corte lo dé PHP.
- La línea ya está aplicada en local: nginx se recargó sin cortar pedidos. Producción quedó anotada en
  `docs/pendientes-prod.md`.

### Encargo de la corrida 1: backend

````text
Tarea: implementar el backend del tramo 2 de "Creación de contenido" en el repo `/var/www/html/nuvads` (Laravel y Vue, sin TypeScript ni Inertia). Trabajás sobre `master`, en este mismo directorio. Sos un subagente: no podés hablar con el usuario, que además no está disponible. Todo lo que haya que preguntarle me lo devolvés a mí en tu informe.

El tramo 2 es el paso 2 de Crear para un solo tipo, Reseñas de clientes, de punta a punta: la tarjeta se prende o se apaga según el material de la marca, al tocarla la app genera ideas con las reseñas reales, y el usuario elige una. Esta corrida hace todo el backend. El frontend lo hace otra corrida después: no toques `resources/js`.

Antes de escribir nada, leé completos:

- `AGENTS.md`: los acuerdos del proyecto. Son obligatorios y un revisor los controla línea por línea.
- `PRODUCT.md` y `docs/objetivo.md`.
- `docs/content-creation.md`: "Modelo de `content_types`" con su catálogo de entradas y "Qué manda cada entrada de Google"; los pasos 1 y 2 de "Qué ve el usuario en Crear"; y "Modelo tentativo de `ideas`".
- `docs/content-creation-progress.md`: la sección "Tramo 2".
- `docs/knowledge-model.md`: la fuente `google_review` y los tipos de conclusión de las reseñas de Google.

Antes de la parte que cubre cada uno, leé completo el skill del proyecto que corresponde: `.claude/skills/capas-backend/SKILL.md`, `.claude/skills/api-backend/SKILL.md` y `.claude/skills/testing-backend/SKILL.md`. Mirá además lo que dejó el tramo 1 (`ContentType`, `ContentTypeRepository`, `ContentTypeService`, `ContentTypeController`, `ContentTypeSeeder`, `tests/Feature/ContentCreation/ContentTypesTest.php`) y cómo llaman al modelo los services de investigación, por ejemplo `GoogleReviewsResearchService`, y seguí esos patrones.

Qué construir:

0. Dos arreglos chicos sobre el tramo 1, que marcó el revisor. En `database/seeders/ContentTypeSeeder.php`, el booleano `$activeContentTypeExists` pasa a llamarse `$hasActiveContentType`, por la regla de nombres de la sección 3 de `AGENTS.md`. Y en ese mismo seeder sumá un comentario corto que diga qué son los `inputs`, nombres de entradas del cerebro cuyas clases están en `app/Services/ContentTypeInputs/` y cuyo mapa está en `config/content.php`, y que `angles` en null es un tipo sin ángulos. No cambies ningún texto de los tipos.
1. Migración `create_ideas_table`. La tabla está autorizada por el usuario (01/10/2026) con exactamente estas columnas, en este orden: `id`; `client_id`, `brand_id` y `content_type_id`, las tres como claves foráneas igual que en las demás tablas por marca; `title` string; `angle` string nullable; `knowledge_insight_ids` json; `knowledge_source_ids` json; `status` string; `model` string; timestamps; soft deletes. `status` es un string abierto: sin enum ni lista cerrada en la base. Hoy su único valor es `chosen`. No agregues ninguna otra columna, índice ni tabla.
2. Modelo `Idea`, con el orden y la configuración de la sección 5 de `AGENTS.md`, `IdeaRepository` e `IdeaService`. Dos operaciones: crear una idea elegida de una marca, y devolver los IDs de fuentes ya usados en las ideas de una marca. Al crear, `status` es `chosen` y `model` sale de la configuración; se valida que el tipo de contenido esté activo, que los IDs de conclusiones y de fuentes sean de esa marca, y que `angle` sea null o uno de los `angles` del tipo.
3. `config/content.php`, con dos cosas: el mapa de nombre de entrada a clase, y el modelo que genera las ideas, `gpt-6-luna`.
4. Las entradas, en `app/Services/ContentTypeInputs/`: una clase por entrada, todas con el mismo contrato. En esta corrida, las cinco de Google: `google_reviews`, `google_review_strengths`, `google_review_products`, `google_review_staff` y `google_review_score`, con el comportamiento exacto de "Qué manda cada entrada de Google". El contrato de una entrada: dice si está vacía para una marca; da el texto, para el usuario, de qué le falta a la marca cuando está vacía; y devuelve su material para una marca, que es el texto para el prompt, los IDs de conclusiones y de fuentes que mandó, y los datos de cada reseña que mandó para poder mostrarla. Sin clase base ni herencia para compartir lógica: cada entrada cuenta su historia completa y se lee sola. Lo que se repite entre las entradas, elegir de una lista de IDs las reseñas que sirven, vive en un solo lugar del dominio de las fuentes. Las entradas leen el cerebro a través de `KnowledgeInsightService` y `KnowledgeSourceService`, y las ideas a través de `IdeaService`; nunca usan repositories ajenos.
5. Disponibilidad de los tipos. `GET /api/content-types` pasa a devolver por cada tipo, además de `id`, `key`, `name` y `description`, si está disponible para la marca del pedido y, cuando no lo está, el texto de por qué. Regla: un tipo está disponible si al menos una de sus entradas tiene material. Un nombre de `inputs` que todavía no tiene clase se ignora; si ningún nombre del tipo tiene clase, el tipo no está disponible y el texto es "Disponible pronto". El texto de las entradas de Google sin material es "Analiza tus reseñas de Google para usar este tipo." Cuando faltan varias, vale el texto de la primera entrada del tipo.
6. Generar propuestas: `POST /api/content-types/{contentTypeId}/idea-proposals`. Es un pedido que espera la respuesta del modelo: sin job, sin cola y sin caché. Nada se guarda en la base.
   - El método del controller empieza subiendo el límite de tiempo a 120 segundos con un helper nuevo, `SystemHelper`, que imita al de Clienty (`/var/www/html/clienty/app/Helpers/SystemHelper.php`, usado así en `/var/www/html/clienty/app/Http/Controllers/API/Actions/EmailController.php:70`). Adaptalo a Nuvads: solo el método `setTimeLimit`, tipado, obtenido con `resolve()` y registrado scoped. Es un pedido explícito del usuario.
   - Un solo service cuenta la historia completa de la generación, de punta a punta y legible de corrido, como los services de investigación: toma el tipo, junta el material de sus entradas con clase, arma el prompt, llama al modelo con `OpenAIHelper::generateJson`, valida y devuelve las propuestas.
   - El prompt, en castellano y en voseo como los que ya existen, lleva: la receta del tipo, que es su `instructions`; sus `angles` cuando los tiene; el material de cada entrada; y de fondo el nombre de la marca y todos sus campos de texto, incluidos los tres `competitors_*`. Tiene que dejar claro que el modelo usa solo las reseñas y los IDs que recibe y que no inventa nada.
   - El modelo devuelve un objeto JSON con la lista de ideas. Cada idea: su título; los IDs de las reseñas que muestra, de una a tres y del mismo grupo cuando sale de un grupo; el ID de la conclusión en que se apoya, cuando sale de un grupo; y el ángulo, que en un tipo sin ángulos es null. El título va en la voz de la marca, según su campo de tono.
   - Validación flexible: PHP descarta de cada idea los IDs que no se mandaron en el material. Una idea que queda sin título o sin ninguna reseña válida se descarta, sin frenar a las demás. No hay cantidad mínima ni máxima de ideas. Si no queda ninguna idea válida, la respuesta es una lista vacía, no un error.
   - La respuesta: la lista de propuestas. Cada una con `title`, `angle`, `knowledge_insight_ids`, `knowledge_source_ids` y, para mostrarlas, sus reseñas con ID, nombre de pila, estrellas, fecha y texto.
   - Un tipo inexistente, borrado o no disponible para la marca responde con un error con `code` propio, según el skill `api-backend`. Los errores del proveedor no se encapsulan: sección 10 de `AGENTS.md`.
7. Elegir una idea: `POST /api/ideas`, con `content_type_id`, `title`, `angle`, `knowledge_insight_ids` y `knowledge_source_ids`. Crea la idea con `IdeaService` y la devuelve.
8. Tests del backend según el skill `testing-backend`, con el modelo simulado con `Http::fake()`. Respetá su presupuesto y justificá en el informe lo que lo supere. Los comportamientos que importan: qué tipos salen disponibles y con qué texto; que las entradas eligen solo reseñas que sirven, sin las ya usadas y sin pasar el tope; que la generación manda el material al modelo y descarta IDs ajenos e ideas sin respaldo; y que elegir una idea la guarda en la marca del pedido y rechaza IDs de otra marca.

Límites:

- No ejecutes la migración ni ningún seeder sobre la base local, ni `migrate:fresh`, ni nada que modifique esa base: los corre el usuario. Los tests usan su propia base, según el skill.
- Ninguna llamada real al modelo ni a otro servicio pago: solo simuladas en los tests.
- Corré los tests de lo que tocaste y los linters del proyecto, y dejá pasando todo lo tuyo. No reformatees archivos ajenos. Si el entorno Docker no está levantado, no levantes ni cambies infraestructura más allá de lo que indica el skill de testing: informalo.
- No toques la configuración de nginx, de PHP ni de Docker, ni el `Makefile`.
- No hagas commits ni operaciones de git que cambien el estado (stash, checkout, reset). Hay cambios sin commitear en `docs/` que no son tuyos. No modifiques nada en `docs/` ni en `resources/js`.
- Fuera de los archivos nuevos, tocá solo lo que el patrón exige: `routes/api.php`, el registro scoped de services, repositories y helpers, y los archivos del tramo 1 que este cambio necesita.
- No agregues dependencias, librerías ni herramientas. No lances otros agentes ni revisores.
- No construyas nada de lo que sigue: las entradas de Educativo, el paso 3, estilos ni piezas. No cambies los textos de `instructions`, `angles` ni `layouts` de los tipos.
- Para pasar atributos a un `create()` seguí el patrón que ya usan los services y repositories existentes, un array de atributos. El revisor le dejó planteada al usuario la duda de si ahí corresponde un DTO: no la resuelvas vos.
- No agregues tests de 401 por endpoint: el skill de testing dice que eso lo cubre `SessionTest`.
- Decisiones de implementación menores que los skills y el patrón existente resuelven: decidilas y anotalas en el informe. Ante una duda de alcance, de producto o de requisitos, o si hiciera falta una tabla, un campo, una dependencia o un parámetro que no está autorizado acá, no la resuelvas con un supuesto: frená esa parte, terminá lo que no depende de ella y devolveme la duda.

Informe final, breve y en castellano: (a) archivos creados y modificados, con una línea por cada uno; (b) el contrato de cada endpoint tal como quedó: ruta, cuerpo que recibe, forma exacta de la respuesta y códigos de error, porque con eso se hace el frontend; (c) el prompt completo que se le manda al modelo, literal; (d) todos los textos visibles para el usuario que escribiste; (e) los comandos que corriste y su resultado real, con la salida si algo falla; (f) las decisiones de implementación que tomaste; (g) lo que no hiciste y por qué; (h) dudas para el usuario. No declares terminado nada que no hayas verificado.
````

### Encargo de la corrida 2: frontend

````text
Tarea: implementar el frontend del tramo 2 de "Creación de contenido" en el repo `/var/www/html/nuvads` (Laravel y Vue, sin TypeScript ni Inertia). Trabajás sobre `master`, en este mismo directorio. Sos un subagente: no podés hablar con el usuario, que además no está disponible. Todo lo que haya que preguntarle me lo devolvés a mí en tu informe.

El tramo 2 es el paso 2 de Crear para un solo tipo, Reseñas de clientes: la tarjeta se prende o se apaga según el material de la marca, al tocarla la app genera ideas con las reseñas reales, y el usuario elige una. El backend ya está hecho. Al mismo tiempo que vos, otra corrida está haciendo ajustes internos en el backend (`app/`, `tests/`, `config/`, `routes/`): no toques nada fuera de `resources/js`, y no te preocupes si ves cambiar esos archivos. Los contratos de abajo no cambian.

Antes de escribir nada, leé completos:

- `AGENTS.md`: los acuerdos del proyecto. Son obligatorios y un revisor los controla línea por línea.
- `.claude/skills/frontend-vue/SKILL.md`: completo, antes de tocar cualquier archivo.
- `PRODUCT.md`, `DESIGN.md` y `docs/objetivo.md`.
- `docs/content-creation.md`: los pasos 1 y 2 de "Qué ve el usuario en Crear".
- `docs/content-creation-progress.md`: la sección "Tramo 2".

Mirá además `resources/js/pages/CreatePage/CreatePage.vue` y `resources/js/services/ContentTypeService.js`, que dejó el tramo 1, y cómo otras páginas, por ejemplo `CompetitorsPage` y `CompetitorPage`, resuelven la carga, los errores, los services y la división en componentes. Seguí esos patrones.

El criterio del usuario para esta pantalla: tiene que ser práctica, y lo visual no importa por ahora porque se va a refactorizar. No inviertas en diseño fino. Sí importan la claridad, que cada estado esté resuelto y que el código cumpla el skill.

Los contratos de la API, ya implementados. Todos responden con el envoltorio `data` y los errores con `code` y `message`, como el resto de la API.

`GET /api/content-types`, 200:

{"data":[{"id":1,"key":"customer_reviews","name":"Reseñas de clientes","description":"Lo que ya dijeron de ti","is_available":true,"unavailable_reason":null},{"id":2,"key":"educational","name":"Educativo","description":"Lo que tus clientes necesitan saber","is_available":false,"unavailable_reason":"Disponible pronto"}]}

`POST /api/content-types/{contentTypeId}/idea-proposals`, sin cuerpo, 200. Tarda: normalmente entre 10 y 30 segundos, y puede llegar a 2 minutos. No guarda nada.

{"data":[{"content_type_id":1,"title":"Lo que más repiten: que te atienden bien","angle":null,"knowledge_insight_ids":[71],"knowledge_source_ids":[512,587],"reviews":[{"id":512,"name":"María","stars":5,"date":"2026-09-20","text":"..."},{"id":587,"name":"Juan","stars":4,"date":"2026-08-02","text":"..."}]}]}

- `data` puede venir `[]`, y no es un error: el material no alcanzó para ninguna idea.
- `reviews` sigue el orden de `knowledge_source_ids`. `name` es el nombre de pila y puede ser null. `text` puede venir cortado, terminado en "...".
- Errores: 404 `content_type_not_found`; 422 `content_type_unavailable`, cuyo `message` es el texto de por qué el tipo no está disponible; 500 o 502 si falla el proveedor.

`POST /api/ideas`, 201. Cuerpo, con los datos de la propuesta elegida tal como llegaron:

{"content_type_id":1,"title":"...","angle":null,"knowledge_insight_ids":[71],"knowledge_source_ids":[512,587]}

- Los IDs son números. `angle` va siempre, null o texto. Las dos listas van siempre, aunque estén vacías.
- Devuelve la idea guardada: {"data":{"id":21,"client_id":3,"brand_id":7,"content_type_id":1,"title":"...","angle":null,"knowledge_insight_ids":[71],"knowledge_source_ids":[512,587],"status":"chosen","model":"gpt-6-luna","created_at":"...","updated_at":"..."}}
- Errores: 422 `validation_failed`; 404 `content_type_not_found`; 422 `idea_angle_invalid`; 422 `idea_references_not_found`, cuyo `message` le dice al usuario que busque otras ideas.

Qué construir, todo dentro de la página Crear (`/create`), sin rutas nuevas en el router:

1. Las tarjetas de tipos usan `is_available` y `unavailable_reason`. Un tipo disponible se puede tocar. Uno no disponible se ve apagado, no se puede tocar y muestra el texto de `unavailable_reason`.
2. Al tocar un tipo disponible, la página pasa a la vista de ideas de ese tipo y pide las propuestas. Mientras espera muestra un estado de espera claro, con un texto que avise que puede tardar un rato. El pedido no se puede disparar dos veces a la vez.
3. La lista de propuestas: arriba el nombre del tipo; cada idea con su título y un renglón chico con la cantidad de reseñas de Google que usa. Al tocar una idea queda elegida y se despliegan sus reseñas: nombre de pila, estrellas, fecha y texto. Hay una sola elegida a la vez.
4. Tres acciones. Seguir con la idea elegida, habilitada solo cuando hay una: la guarda con `POST /api/ideas` y muestra la idea guardada, con sus reseñas y un texto que diga que quedó guardada y que el paso siguiente todavía no está disponible. Buscar otras ideas: vuelve a pedir propuestas y reemplaza la lista. Volver a los tipos: vuelve a las tarjetas.
5. Las propuestas viven en la memoria de la página. Si el usuario vuelve a los tipos y entra otra vez al mismo tipo sin salir de Crear, ve las propuestas que ya tenía, sin pedirlas de nuevo: cada pedido cuesta plata. Solo "Buscar otras ideas" pide de nuevo. Al salir de la página se pierden. No uses `localStorage` ni nada que persista.
6. Estados: la lista vacía dice que con el material actual no salieron ideas y deja buscar otras; un error muestra el `message` que devuelve la API, como hacen las otras páginas, y deja reintentar.
7. Las llamadas a la API van en services de JS según el skill: la de propuestas y la de guardar la idea.

Los textos que ve el usuario van en español neutro, de tú, como el resto de la app.

Límites:

- No toques nada fuera de `resources/js`. No agregues rutas al router ni entradas al menú.
- No agregues dependencias, librerías ni herramientas. No lances otros agentes ni revisores.
- No hagas commits ni operaciones de git que cambien el estado. No modifiques nada en `docs/`.
- No levantes servidores ni Vite, no abras la aplicación y no hagas pedidos reales a la API: generar ideas cuesta plata y la tabla `ideas` todavía no existe en la base local. Verificá con ESLint, que tiene que quedar pasando, y con un build de prueba que no escriba en `public/build`.
- No construyas nada de lo que sigue: el paso 3, estilos ni piezas.
- Decisiones de implementación menores que el skill y el patrón existente resuelven: decidilas y anotalas en el informe. Ante una duda de alcance, de producto o de requisitos, no la resuelvas con un supuesto: frená esa parte, terminá lo que no depende de ella y devolveme la duda.

Informe final, breve y en castellano: (a) archivos creados y modificados, con una línea por cada uno; (b) todos los textos visibles para el usuario que escribiste, literales, y cuándo aparece cada uno; (c) los comandos que corriste y su resultado real; (d) las decisiones de implementación que tomaste; (e) lo que no hiciste y por qué; (f) dudas para el usuario. No declares terminado nada que no hayas verificado.
````

### Lo que salió en el camino

`revisor-nuvads` sobre el commit del tramo 1, `be7d3ea`, con Sonnet: ningún hallazgo estructural, dos de estilo y dos
dudas para el usuario.

- Estilo, se corrige en la corrida 1: en `ContentTypeSeeder`, `$activeContentTypeExists` pasa a
  `$hasActiveContentType`.
- Estilo, queda para el usuario: `CreatePage.vue` usa `max-w-[1296px]`, un valor suelto que ya estaba en otras tres
  páginas. La regla pide centralizarlo en `variables.css`, y corregirlo toca páginas ajenas. El usuario dijo que lo
  visual se va a refactorizar.
- Duda para el usuario: si la regla de "array de hasta tres claves, si no DTO" alcanza a los atributos que se pasan a
  un `create()`. Hoy todos los `create` y `update` del proyecto reciben un array de atributos. Los tramos siguen ese
  patrón hasta que el usuario decida.
- Duda para el usuario: si hace falta un comentario en el código que diga qué son `inputs` y `angles`. Se suma uno
  corto en el seeder en la corrida 1; el usuario puede sacarlo.
- Fuera de los acuerdos: el encargo del tramo 1 pedía un test de sesión por endpoint, y el skill de tests dice que
  eso lo cubre `SessionTest`. Valía el skill; el encargo estaba mal en ese punto.
- Fuera de los acuerdos: el cuerpo del mensaje del commit `be7d3ea` usa la palabra "tramo", que no es inglés. El
  commit ya está pusheado y no se reescribe; los próximos mensajes van enteros en inglés.

Corrida 1, backend, con Opus: terminó sin dudas que frenaran el trabajo.

- Entregó la migración de `ideas`, `Idea`, `IdeaRepository`, `IdeaService`, `IdeaGenerationService`, `IdeaController`,
  `CreateIdeaRequest`, la interfaz `ContentTypeInput` y las cinco entradas de Google, dos DTO, `SystemHelper`,
  `config/content.php`, las dos rutas nuevas y los tests de `tests/Feature/ContentCreation/`.
- `GET /api/content-types` suma `is_available` y `unavailable_reason`. `POST /api/content-types/{id}/idea-proposals`
  devuelve las propuestas con sus reseñas, sin guardar nada. `POST /api/ideas` guarda la idea elegida.
- Verificado por esta sesión: la migración tiene las columnas aprobadas; el controller sube el límite de tiempo como
  en Clienty; la generación descarta los IDs que no se mandaron y las ideas sin respaldo; los tests del tramo pasan.

Decisiones que tomó esta sesión sobre lo que planteó el subagente, dentro de lo que el usuario dejó a su criterio:

- Se quita `KnowledgeSourceService::pickGoogleReviewsForIdeas`. Es un método con nombre de un subtipo de fuente, y el
  usuario tiene dicho que un subtipo no lleva métodos propios en el service del modelo. Cada entrada elige sus
  reseñas con los métodos genéricos que ya existen.
- Las reseñas sueltas también se sortean de nuevo cuando todas las que sirven ya están en ideas, como los grupos.
- Una idea que la generación descarta, o un ID que el modelo inventó, queda registrado con `report()`, por la
  sección 10 de `AGENTS.md`.
- En la pantalla la reseña se muestra cortada a 300 caracteres, como la vio el modelo.
- El prompt y la regla de "al menos una reseña por idea" quedan escritos para reseñas hasta que llegue Educativo.

Dudas del subagente que esperan al usuario:

- Si se guarda un log de cada generación, con el prompt y la respuesta, para evaluar si las ideas salen buenas. Hoy,
  si todo sale bien, no queda registro.
- Si el puntaje de Google solo, sin ninguna reseña que sirva, debería prender la tarjeta. Hoy la prende, y la
  generación paga una llamada para devolver una lista vacía.
- Disponibilidad: saber si un tipo tiene material calcula el material completo de la primera entrada que lo tenga.
  Con miles de reseñas, la pantalla de tipos carga todas en cada visita. Sirve por ahora; es para optimizar.

`revisor-nuvads` sobre el backend de la corrida 1, con Sonnet: dos hallazgos estructurales, dos de estilo y tres dudas
para el usuario. Las correcciones se le pasaron al mismo subagente del backend.

- Estructural, se corrige: las comprobaciones de `POST /api/ideas` (el tipo existe, el ángulo es del tipo, las
  conclusiones y las fuentes son de la marca) estaban en `IdeaService` y fallaban con excepciones propias. El skill
  de API pide que vayan en `after()` del request y salgan como 422 `validation_failed` con errores por campo. El
  encargo las había pedido en el service: estaba mal en ese punto.
- Estructural, se corrige: lo que la generación descartaba o corregía de la respuesta del modelo no quedaba
  registrado. Ahora queda con `report()`.
- Estilo, se corrige: orden de líneas por longitud en tres lugares, un comentario en `SystemHelper` sobre por qué
  hace dos llamadas, comentarios en dos arrays chicos y comentarios de tests de más de dos líneas.
- Duda para el usuario: `content_type_unavailable` responde 422. El revisor pregunta si va 409. Se deja 422 porque el
  caso parecido que ya existe, `competitor_link_missing`, usa 422.
- Duda para el usuario: los arrays de cuatro claves que solo se serializan a JSON para el prompt, sin DTO. El código
  que ya existe hace lo mismo. Es la misma familia que la duda de los atributos de `create()`.
- Duda para el usuario: si quedan acordados el nombre `generateProposals` y la ruta
  `content-types/{contentTypeId}/idea-proposals`, que no están entre los verbos habituales.

Corrida 2, frontend, con Opus: terminó sin dudas que frenaran el trabajo.

- Entregó `IdeaService.js`, los cambios en `CreatePage.vue` y dos componentes nuevos de la página:
  `IdeaProposalsStep.vue`, el paso 2 completo, e `IdeaReviewList.vue`, las reseñas de una idea.
- Verificó con ESLint, con un build de prueba que no escribe en `public/build`, y con una prueba de comportamiento
  armada fuera del repo sobre los componentes reales, con dobles de los services. No abrió la app ni pidió nada a la
  API.
- Las propuestas de cada tipo viven en la memoria de la página: volver a los tipos y entrar de nuevo no vuelve a
  pedirlas. Se pierden al salir de Crear.

Decisiones que tomó esta sesión sobre lo que planteó el subagente del frontend, dentro del pedido de que la pantalla
sea práctica:

- Después de guardar una idea, sale de la lista de propuestas, para que no se guarde dos veces.
- Si "Buscar otras ideas" falla, la lista anterior se conserva y el error se muestra encima: ya se pagó.
- Se suma el aviso del diseño para cuando ningún tipo está disponible, con el enlace a Mi marca.
- Al fallar el guardado se muestra el primer error de campo, por el cambio de la validación al request.
- No se mueve `BrandStarRating` a los componentes compartidos: toca Mi marca, y lo visual se va a refactorizar.

Correcciones aplicadas por los dos subagentes, verificadas por esta sesión:

- Backend: el método con nombre de subtipo ya no existe y `KnowledgeSourceService` quedó igual que antes del tramo;
  la validación de `POST /api/ideas` está en `CreateIdeaRequest::after()`; lo que la generación descarta se registra
  con `report()`; y los arreglos de estilo. `ContentTypeService::find` pasó a devolver null, y el 404 de
  `idea-proposals` lo lanza `IdeaGenerationService`, porque el request no puede lanzar excepciones.
- Frontend: los cuatro ajustes de arriba.
- La suite completa pasa, con el mismo test salteado de antes, y Pint, PHPCS y ESLint pasan.

`revisor-nuvads`, segunda pasada, sobre el frontend y lo corregido en el backend, con Sonnet: ningún hallazgo
estructural, uno de estilo y cinco dudas para el usuario.

- Estilo, corregido: booleanos sin el prefijo `is`, `has` o `can` en `IdeaGenerationService`, en `IdeasTest` y en
  `IdeaProposalsStep.vue`.
- Se resolvió con las reglas escritas: los errores de `OpenAIHelper` traen el cuerpo de OpenAI en el mensaje y, como
  la generación es síncrona, ese texto llegaba a la pantalla. Ahora la generación lanza una `ApiException` con
  mensaje propio, `idea_generation_failed`, y la original como `previous`, como pide la sección 10 de `AGENTS.md`.
- Se resolvió con el patrón del proyecto: el tipo inexistente en `idea-proposals` usa `ModelNotFoundException`, 404
  `not_found`, como los demás services, en lugar de un código propio.
- Duda para el usuario: `CreateIdeaRequest` no define `messages()` y el proyecto no tiene traducciones, así que un
  fallo de `rules()` saldría en inglés. Los campos los arma la app, no los tipea el usuario: solo pasaría ante un bug.
- Duda para el usuario: el patrón de una línea que muestra el primer error de campo, que ya usan otras páginas, no
  está partido en variables con nombre.
- Duda para el usuario: si el orden de líneas por longitud aplica al armado de los tests, que hoy siguen la narración.
- Fuera de los acuerdos: `formatDate` y las estrellas repiten lo que ya hacen componentes de Mi marca. El skill no
  dice dónde compartir funciones; queda para el refactor visual.

Cierre de esta sesión, el 01/10/2026:

- Últimos arreglos del backend: los booleanos renombrados, el 404 del tipo inexistente con el patrón del proyecto, y
  los errores de `OpenAIHelper` envueltos en `idea_generation_failed` con la original como `previous`.
- Verificado por esta sesión al final: la suite completa pasa, 144 tests con el mismo salteado de antes; Pint, PHPCS
  y ESLint pasan; un build de prueba del frontend compila. La única migración pendiente en la base local es la de
  `ideas`. Nadie vio la pantalla en un navegador ni hizo una generación real.
- En la base local, los dos tipos ya tienen las entradas nuevas: el usuario corrió el `UPDATE`. Up! tiene 620 reseñas
  de Google y Clienty ninguna, así que con Up! la tarjeta de Reseñas sale prendida y con Clienty apagada.

### Para el usuario al volver

Esta lista es la del cierre del 01/10/2026 y quedó como registro. El usuario ya corrió la migración, probó el paso 2
y contestó las dudas. Lo vigente está en "Para retomar", al principio del documento.

Qué correr:

```bash
docker compose --env-file .env.docker --file compose.yaml exec -T php php artisan migrate
```

Después, entrar a Crear con Up! y tocar Reseñas de clientes. Esa generación es una llamada real y paga al modelo.

Qué decidir:

- Decidido el 01/10/2026: por ahora no se guarda un log de cada generación. El usuario lo prueba así.
- Decidido y hecho el 01/10/2026: el puntaje de Google no alcanza para prender la tarjeta. Las entradas dicen si
  alcanzan para sostener ideas con `canSupportIdeas()`, y la tarjeta apagada distingue entre no haber analizado las
  reseñas y haberlas analizado sin que ninguna sirva. Una salvedad del subagente: ese segundo texto sale de la
  primera entrada del tipo, y sería inexacto en un tipo que empezara por una entrada de grupo. Hoy no hay ninguno.
- Las dudas de convenciones que dejó el revisor, respondidas por el usuario el 01/10/2026:
  - Atributos de `create()`: los services de creación pueden recibir arrays, no hay problema. Queda como está.
  - Nombres: un modelo `Idea` sin guardar tiene que llamarse idea, no `$proposal`. Aplicado en todo el código: el
    método del controller y el del service son `generateSuggestedIdeas`, las variables `suggestedIdea` y
    `suggestedIdeas`, la ruta `POST /api/content-types/{contentTypeId}/suggested-ideas` y el componente
    `SuggestedIdeasStep.vue`. La palabra "proposal" ya no aparece en el código. Los encargos de arriba conservan los
    nombres viejos porque son el texto que recibieron los subagentes.
  - 422 o 409 para el tipo sin material: a criterio del agente. Queda 422, como `competitor_link_missing`.
  - `messages()` en `CreateIdeaRequest`: se obvia por ahora, porque solo se vería ante un bug.
  - Orden de líneas en los tests: se obvia en esta pasada.
  - La línea que mostraba el primer error de campo juntaba cuatro operaciones. El usuario le dio la razón al
    revisor: en el código siempre se privilegia la baja carga cognitiva. En Crear quedó partida en pasos con nombre,
    igual que otras líneas compuestas de la página. La misma línea estaba en otros cinco lugares de Mi marca y
    Competencia, anteriores a este tramo, más una variante: el usuario pidió corregirla en todos lados y sin función
    compartida, porque prefiere que cada componente tenga su lógica antes que acoplarlos. Quedó partida en cada
    lugar: `BrandResearchPanel.vue`, `BrandVisualIdentity.vue`, `BrandUploadedFilesPanel.vue`,
    `CompetitorResearchPanel.vue` y `brandUploadedFileModalStore.js`.
  - Atributos de `create()`: la excepción quedó escrita en el skill de capas, con el texto que aprobó el usuario.
    Vale para crear un modelo; dice además cuándo sí va un DTO, uno que sabe armarse desde otra estructura y expone
    `toArray()`. No nombra los `update` ni los arrays que se arman para el prompt, que quedan como están.
- Un texto: si el usuario guarda la última propuesta de la lista y vuelve a entrar, la pantalla dice que no salieron
  ideas, que no es exacto.
- Para el refactor visual: el ancho fijo repetido en cuatro páginas, y `formatDate` y las estrellas repetidos entre
  Crear y Mi marca.
- Para optimizar: saber si un tipo tiene material arma el material completo de una entrada en cada visita a Crear.
- Fuera de este tramo: el mensaje general de validación del handler dice "Revisá los campos indicados.", en voseo,
  mientras el resto de la app habla de tú.
