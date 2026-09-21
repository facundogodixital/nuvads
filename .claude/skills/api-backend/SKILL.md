---
name: api-backend
description: "Convenciones de Nuvads para la API backend: estructura de las respuestas JSON, flujo de validación en los requests, controllers de API y gestión central de errores. Cargar siempre antes de crear o modificar endpoints, requests, controllers, resources, excepciones o el handler de errores."
---

# API backend de Nuvads

Estas convenciones aplican junto con las reglas generales de AGENTS.md (legibilidad, espaciado, nomenclatura, capas).

## 1. Estructura de las respuestas JSON

Decisión: el estado HTTP indica el resultado de la petición. No hay campo `success`.

Respuestas exitosas:
- El contenido va dentro de `data`.
- Estado HTTP según el caso: 200, o 201 cuando se crea un recurso.

Errores:
- `code` y `message` en la raíz, sin envolverlos en un objeto `error`.
- `code` es un identificador estable en snake_case, por ejemplo `order_already_shipped`. No es el estado HTTP. El frontend decide según `code`, nunca según `message`.
- `message` es un texto legible para el usuario.
- `errors` solo cuando hay errores de validación por campo: un objeto con el campo como clave y una lista de mensajes como valor.
- Estado HTTP apropiado: 409 conflicto, 422 validación, 500 inesperado.

Errores inesperados:
- `code: "internal_error"` y un mensaje genérico. Nunca se expone el mensaje original de la excepción.
- En desarrollo, con debug activado, se agrega un campo `debug` en la raíz con la clase de excepción, archivo, línea y trace, sin los argumentos de las llamadas. En producción esos detalles quedan en los logs.
- El registro de errores funciona independientemente de mostrar debug.

Listados paginados: **pendiente**. No se usa la estructura de los Resource collections de Laravel (`data`, `links` y `meta`). Se definirá con el primer listado.

Motivo: separar el estado HTTP, la identificación del problema y el mensaje para el usuario, conservando la estructura de Laravel para `message` y `errors` y agregando `code` como convención propia.

Ejemplo de éxito — HTTP 201:

```json
{
    "data": {
        "id": 15,
        "name": "Mesa"
    }
}
```

Ejemplo de error de negocio — HTTP 409:

```json
{
    "code": "order_already_shipped",
    "message": "No se puede cancelar un pedido que ya fue enviado."
}
```

Ejemplo de validación — HTTP 422:

```json
{
    "code": "validation_failed",
    "message": "Revisá los campos indicados.",
    "errors": {
        "email": ["El correo ya está registrado."],
        "name": ["El nombre es obligatorio."]
    }
}
```

Ejemplo de error inesperado en producción — HTTP 500:

```json
{
    "code": "internal_error",
    "message": "Ocurrió un error inesperado."
}
```

Ejemplo de error inesperado en desarrollo — HTTP 500:

```json
{
    "code": "internal_error",
    "message": "Ocurrió un error inesperado.",
    "debug": {
        "exception": "TypeError",
        "file": "/var/www/html/nuvads/app/Services/OrderService.php",
        "line": 42,
        "trace": [
            {
                "file": "/var/www/html/nuvads/app/Http/Controllers/OrderController.php",
                "line": 18,
                "function": "cancel",
                "class": "App\\Services\\OrderService",
                "type": "->"
            }
        ]
    }
}
```

## 2. Flujo común de validación en los requests

Decisión: todos los requests deben seguir el mismo recorrido de validación.

Orden de los métodos: `rules()` primero y `messages()` inmediatamente después, cuando exista. Después van los demás métodos del request.

Paso 1 — Reglas de entrada:
`rules()` declara los campos obligatorios, tipos, formatos, límites y demás reglas que Laravel pueda expresar directamente. Laravel las evalúa con su comportamiento por defecto y acumula los errores de todos los campos.

Paso 2 — Comprobaciones adicionales:
Usar `after()` como única convención para esta etapa, sin alternarlo con `withValidator()`. Si `rules()` resuelve toda la validación, omitir `after()`.

El callback de `after()` primero comprueba si ya hay errores de `rules()`. Si los hay, termina sin ejecutar consultas ni procesar datos inválidos.

Paso 3 — Registro de errores:
En `after()` no se acumulan errores. Cada comprobación, si falla, agrega su error al campo correspondiente mediante `$validator->errors()->add()` y termina el callback con `return`. Las comprobaciones siguientes no se ejecutan.

Cada comprobación se escribe asumiendo que todas las anteriores pasaron. No lanzar excepciones propias ni devolver false para señalar un fallo de validación.

Paso 4 — Resultado de la validación:
Laravel decide el resultado. Si hay errores, interrumpe la petición y el manejo central de excepciones presenta el JSON acordado con HTTP 422. Si no hay errores, Laravel ejecuta el controller.

Paso 5 — Obtención de los datos:
El controller obtiene los datos desde el request, ya validados. La forma habitual es `$request->validated()`. Cuando conviene para bajar carga cognitiva, el request puede exponer getters que devuelvan algo puntual, o construir un DTO con los datos validados. En todos los casos la fuente es lo que pasó la validación, nunca `$request->all()` ni `$request->input()` sin validar.

Plantilla para las comprobaciones adicionales:

```php
use Illuminate\Validation\Validator;

public function after(): array
{
    return [
        function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->mixesStatusesAndTags()) {
                $validator->errors()->add(
                    'triggering_status_ids',
                    'No se pueden combinar estados y etiquetas de disparo.'
                );
                return;
            }

            if ($this->tagBelongsToAnotherClient()) {
                $validator->errors()->add(
                    'tag_id',
                    'La etiqueta no pertenece al cliente.'
                );
                return;
            }
        },
    ];
}
```

Motivo: mantener una mecánica uniforme y reconocible en todos los requests, con el mismo orden de ejecución y la misma forma de comunicar los errores. En `after()` se corta en el primer fallo porque es más simple y práctico: cada comprobación trabaja sobre datos que ya pasaron todo lo anterior.

## 3. Controllers de API

Alcance: estas reglas aplican únicamente a los controllers de la API. Los controllers que devuelvan vistas, si los hubiera, tienen otra lógica y no están alcanzados. Los controllers de test, de crons o de workers NO están obligados a seguir estas reglas: su estructura la define su función.

Decisión: un controller hace tres cosas, en este orden y a la vista: obtiene la entrada, ejecuta la operación y construye la respuesta HTTP. Se prioriza la lectura sobre la cantidad de líneas: los pasos pueden separarse en variables con nombres claros.

Entrada:
- Los parámetros del método son solo lo que recibe la acción: el request, los parámetros de ruta y los modelos que llegan por route model binding.
- Los datos de entrada se obtienen desde el request, ya validados, según el paso 5 del flujo de validación.

Operación:
- La lógica de negocio, las consultas y las transformaciones se delegan a los services.
- Los services se obtienen con `resolve()` dentro del método, junto a la llamada. Es la única forma de obtenerlos: no se inyectan como parámetros del método aunque Laravel lo permita, y no se instancian con `new`.

```php
// Prohibido:
public function create(CreateTagRequest $request, TagService $service)

// Correcto:
public function create(CreateTagRequest $request)
{
    $tag = resolve(TagService::class)->create(...);
}
```

- Un método puede llamar a más de un service si el recorrido sigue siendo claro. Pero si esa coordinación incluye decisiones o reglas de negocio, se mueve al service responsable. No se crean métodos intermediarios solo para que el controller haga una sola llamada.
- Si el modelo llegó por route model binding y solo hay que devolverlo, no hace falta pasar por un service.

Respuesta:
- El controller elige la representación del resultado y el estado HTTP. Usa Resources cuando corresponde y respeta el formato JSON de la sección 1.
- Los errores los presenta el handler central (sección 4). No se escriben try/catch en el controller solo para armar respuestas de error.

Nombres de los métodos: no se usa `apiResource` ni los nombres de acción de Laravel (`index`, `store`, `show`, `destroy`). Los controllers usan estos verbos:
- `create`: crear.
- `update`: modificar.
- `delete`: eliminar.
- `find`: obtener uno.
- `list`: listar.

Habrá otros métodos distintos, que se definirán en su momento.

Ejemplo:

```php
public function create(CreateTagRequest $request): JsonResponse
{
    $data = $request->validated();

    $tag = resolve(TagService::class)->create($data);

    $resource = new TagResource($tag);

    return $resource->response()->setStatusCode(201);
}
```

## 4. Gestión central de errores

Decisión: la presentación de errores y el criterio de reporte a Sentry se centralizan en `App\Exceptions\Handler`, que extiende el handler de Laravel y se registra en `bootstrap/app.php` como implementación de `ExceptionHandler`.

Excepciones:
- Una única `ApiException` compartida para comunicar un error con estado HTTP, código estable y mensaje público. Recibe `httpStatus`, `errorCode` y `message` explícitamente. El mensaje se devuelve al usuario, así que debe ser legible y sin detalles técnicos.
- No se crea una clase de excepción por cada error de negocio. Solo se crea una excepción particular cuando haga falta identificarla por su tipo en un catch.
- Para fallos genéricos se puede usar `Exception`. Su código numérico no se interpreta como estado HTTP ni su mensaje se publica.
- Las excepciones nativas de Laravel se conservan para los casos que ya resuelve el framework, incluida la validación de los requests.

Presentación:
- El handler trabaja por categorías: `ApiException`, validación, autenticación, errores HTTP y fallos inesperados (`Throwable`, incluidos los errores de PHP).
- `ApiException` ya transporta los datos de su respuesta. Agregar un error de negocio no requiere tocar el handler.
- Los errores nativos conservan su estado HTTP y sus cabeceras.
- Todas las respuestas respetan el formato y las condiciones de debug de la sección 1.
- No se escriben try/catch en los controllers para armar respuestas de error. Las excepciones llegan al handler.

Reporte:
- Armar la respuesta y reportar el error son responsabilidades separadas. Mostrar debug no determina si se registra o reporta.
- El criterio de envío a Sentry vive en el handler, con dos listas de exclusión (por tipo de excepción y por código de `ApiException`) que arrancan vacías.
- Excluir un error de Sentry no lo excluye de los logs de Laravel. Un fallo al enviar a Sentry no impide registrar el error original.

Ejemplo:

```php
throw new ApiException(
    httpStatus: 409,
    errorCode: 'order_already_shipped',
    message: 'No se puede cancelar un pedido que ya fue enviado.',
);
```

Motivo: un único lugar para presentar y reportar errores, sin convertir el handler en un catálogo de errores de negocio ni multiplicar clases de excepción sin necesidad.
