# Creación de contenido: avance de la implementación

Tablero de la implementación, iniciado el 01/10/2026. El diseño está en [content-creation.md](content-creation.md) y
el objetivo en [objetivo.md](objetivo.md); este archivo no los repite. Acá va qué tramo se está haciendo, qué se le
encargó y qué salió en el camino.

Cómo se trabaja: cada tramo se encarga a un subagente con Opus, sobre `master`, con un encargo aprobado antes por
el usuario. Ante cualquier duda, el subagente frena y la devuelve; se resuelve con el usuario y sigue. Al terminar,
se revisa contra el diseño. Los commits se hacen cuando el usuario los pide.

## Tramos

Lista propuesta, a ajustar. Cada tramo arranca con el ok del usuario.

| Tramo | Qué incluye | Estado |
| --- | --- | --- |
| 1 | `content_types`: tabla, modelo, repository, service, la carga de dos tipos, el endpoint que los lista y la pantalla del paso 1 con las tarjetas. | Hecho y commiteado |
| 2 | `styles`: tabla, modelo, repository, service y la carga de los estilos. | Pendiente |
| 3 | Entradas del cerebro, y tarjetas prendidas o apagadas según el material de la marca. | Pendiente |
| 4 | Paso 2: generar ideas de un tipo y elegir una. | Pendiente |
| 5 | Paso 3: escribir formato, guión y copy. | Pendiente |
| 6 | Paso 4: `pieces`, `piece_images` y dibujar. | Pendiente |

## Tramo 1: `content_types`

Estado: hecho por un subagente con Opus sobre `master`, revisado y commiteado el 01/10/2026. El usuario ya corrió
la migración y el seeder. Falta que confirme que la pantalla se ve bien.

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
