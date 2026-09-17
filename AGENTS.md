# Acuerdos de trabajo de Nuvads

## 1. Ante cualquier duda, preguntar al usuario

> Ante cualquier duda me preguntás. Si te parece bien agregar algo, me preguntás.

- Esta es la primera regla de trabajo del proyecto y debe conservarse como tal al ampliar estas instrucciones.
- Ante cualquier duda sobre el alcance, los requisitos o una decisión de implementación, consultar al usuario y esperar su respuesta antes de avanzar con esa parte del trabajo.
- No resolver dudas mediante supuestos ni interpretar una propuesta del agente como una decisión aprobada.
- Antes de incorporar por iniciativa propia tecnologías, frameworks, librerías o herramientas adicionales, explicar la propuesta, preguntar y esperar aprobación explícita.
- No elegir ni aplicar parámetros de infraestructura sin consultarlos previamente con el usuario. Esto incluye puertos, dominios, versiones, redes, volúmenes, rutas de montaje y credenciales.
- Una orden general de instalar o montar el entorno no autoriza a decidir los detalles pendientes. Presentar esos detalles al usuario y esperar su confirmación antes de aplicarlos; ante cualquier duda, preguntar.
- El stack autorizado es Laravel y Vue. El usuario rechazó TypeScript e Inertia: no incorporarlos sin consultar.

## 2. Convenciones detalladas: skills del proyecto

Las convenciones con plantillas y ejemplos viven en `.claude/skills/` (también accesibles vía `.agents/skills/`). Leer el archivo del skill correspondiente ANTES de trabajar en la parte que cubre:

- `.claude/skills/api-backend/SKILL.md`: respuestas JSON, validación de requests, controllers de API y gestión central de errores.
- `.claude/skills/capas-backend/SKILL.md`: services, repositories y helpers (responsabilidades, acceso entre capas, nombres de métodos).
- `.claude/skills/frontend-vue/SKILL.md`: modales con Pinia, estructura de componentes Vue y llamadas a la API desde el frontend.

## 3. Legibilidad y carga cognitiva

Principio: el código tiene que poder leerlo una persona y entenderlo mientras lo lee. Toda decisión de forma se toma con ese criterio. Aplica al backend y al frontend.

### Bloques

- Se prefiere un bloque un poco más largo y legible antes que cinco métodos de dos líneas que obligan a ir y venir para entender una pieza.
- Bajo acoplamiento, siempre que no agregue carga cognitiva.
- Cohesión: un método hace lo que dice su nombre. Un `getInfo()` que además hace tres cosas adentro no es aceptable.

### Condicionales

Las condiciones se desarman en booleanos con nombre, y el `if` se escribe sobre esos booleanos. Dos o tres líneas más a cambio de un condicional que se lee solo.

```php
// NO
if (($dto->getMessageInfo() !== self::BAD_INFO_MSG) || $request->response?->code === self::RESPONSE_500) { ... }

// SÍ
$isBadMsg = $dto->getMessageInfo() !== 'bad_info';
$isError500 = $request->response?->code === 500;
if ($isBadMsg || $isError500) { ... }
```

Las constantes no están prohibidas, pero se evalúa si hacen falta y cuánta carga cognitiva agregan.

### Idioma

El código siempre en inglés: variables, métodos, clases, archivos. Los comentarios en castellano.

### Nombres

Variables y métodos todo lo descriptivos que se pueda.

```php
$isValid = $lead->message === 'ok';            // NO
$leadHasValidMsg = $lead->message === 'ok';    // SÍ

WhatsAppService::canView()                     // NO
WhatsAppService::canViewInboxMessage()         // SÍ
```

### Retorno

Los métodos devuelven algo. Se evita recibir un parámetro, operarlo por referencia y devolver void. Los void no están prohibidos, pero el caso tiene que ser claro: el método ejecuta una acción, no transforma un dato.

```php
$service->fillDto($dto);           // NO
$dto = $service->fillDto($dto);    // SÍ
```

### Strings

Comillas simples para strings literales sencillos, incluidas las claves de array. Comillas dobles solo cuando el string es compuesto: `"string_con_{$valor}_de_{$variable}"`.

### Orden de líneas

Cuando se puede, las líneas de un mismo bloque se ordenan de menor a mayor longitud. Aplica a los `use` y a bloques de asignaciones.

```php
use App\Models\Lead;
use App\Services\API\LeadService;
use App\Http\Resources\LeadResource;
use App\Http\Requests\GetLeadRequest;

$leadAttrs['hash'] = $hash;
$leadAttrs['status_id'] = $status->id;
$leadAttrs['lead_created_at'] = new DateTime();
```

### Comentarios

- Donde algo por diseño no pueda quedar del todo claro, un comentario corto que dé una pista o explique qué se hace.
- Sin comentarios largos, salvo pedido o necesidad.

### Espaciado en clases PHP

- `namespace`, una línea en blanco, bloque de `use`, dos líneas en blanco, `class`.
- Después de la llave de apertura, una línea en blanco. Luego, en este orden y separados por una línea en blanco: bloque de `use` de traits si existen, bloque de `const` si existen, bloque de atributos si existen.
- Dos líneas en blanco antes del primer método y entre cada método y el siguiente.
- Una línea en blanco entre el último método y el cierre de la clase.
- En los archivos .vue, dos líneas en blanco entre template, script y style (ver skill `frontend-vue`).

```php
namespace App\Services;

use App\Models\Tag;
use App\Models\Lead;
use App\Models\Client;


class LeadService
{

    use HasClientScope;

    const int MAX_TAGS = 1;
    const int MAX_RETRIES = 2;

    public int $count = 0;
    private ?User $user = null;


    public function __construct()
    {
        ...
    }


    public function anotherMethod(): void
    {
        ...
    }

}
```

### Tipado

Tipar siempre que se pueda: parámetros y retorno de los métodos, atributos de clase, y todo lo demás que el lenguaje permita tipar.

Excepción: las propiedades que Eloquent declara sin tipo en el Model padre (`$fillable`, `$hidden`, etc.) van sin tipo — PHP no permite tipar en el hijo lo que el padre declara sin tipo — y sin anotación `@var`.

### Indentación

PHP con 4 espacios. JavaScript y archivos .vue con 2 espacios.

## 4. Forma clásica de Laravel, sin atributos PHP

No usar atributos PHP con sintaxis `#[...]` en el código Laravel de Nuvads. Usar las formas clásicas equivalentes mediante propiedades, métodos y configuración explícita, según corresponda: las propiedades `$fillable` y `$hidden` en modelos, los métodos `scopeNombre` para scopes, las propiedades `$tries` y `$timeout` para configurar jobs, etc.

Excepción: los tests. PHPUnit 12 solo admite `#[Test]`, `#[DataProvider]` y similares como atributos, por lo que en tests se usan.

Motivo: el usuario prefiere visualmente la forma clásica porque le resulta más legible y le genera menos carga cognitiva.

```php
class Product extends Model
{

    protected $fillable = ['name', 'price'];

}
```

## 5. Nomenclatura de modelos, servicios, repositorios y tablas

- Modelos en singular y UpperCamelCase: `Product`, `OrderItem`.
- Servicios y repositorios asociados a un modelo: nombre del modelo en singular con el sufijo `Service` o `Repository`: `ProductService`, `ProductRepository`.
- Tablas y columnas con la convención por defecto de Laravel: tablas en plural snake_case (`products`, `order_items`) y columnas en snake_case. No se declara `$table` en los modelos para cambiar esa convención; Eloquent infiere el nombre de la tabla a partir del modelo.
- Tablas pivot y claves foráneas también con la convención por defecto de Laravel: `product_tag`, `product_id`.

Motivo: se descartó UpperCamelCase en tablas por los problemas de mayúsculas en MySQL y la configuración extra que exige.

## 6. Capas: mapa de acceso (resumen)

El detalle completo está en el skill `capas-backend`. Este mapa aplica siempre:

- Service: punto de entrada a las operaciones y los datos de un dominio; aplica las reglas de negocio.
- Repository: consultas y persistencia de un dominio en la base de datos del sistema.
- Helper: tareas técnicas auxiliares o comunicación con un sistema externo.
- Controllers, jobs, comandos y demás consumidores acceden a los datos solo mediante services. No usan repositories ni modelos directamente para consultar o persistir.
- Un service accede a otro dominio a través del service de ese dominio, no de su repository: `LeadService -> TagService -> TagRepository`.
- Services y helpers se obtienen con `resolve()` dentro del método que los usa, cerca de su uso. La única inyección por constructor es la del repository propio en su service. No se inyectan services como parámetros de los métodos de los controllers.
- Services, repositories y helpers se registran como scoped por defecto. Usar singleton u otra duración requiere una decisión explícita.
- Verbos de métodos en controllers, services y repositories: `create`, `update`, `delete`, `find`, `list` (más `findOneBy...`, `findBy...`, `countBy...`, `get...` en services y repositories).
- Las excepciones a esta estructura requieren una decisión explícita del usuario.

## 7. Revisión al cerrar tareas

Al terminar una tarea de programación, antes de darla por cerrada, se revisan los cambios contra estos acuerdos. El procedimiento del revisor vive en `.claude/agents/revisor-nuvads.md` y es la única fuente.

- Claude Code usa su subagente `revisor-nuvads`.
- Codex usa el skill `revisar-nuvads` (exclusivo de Codex; Claude no lo usa).
- Quien invoca al revisor le pasa el alcance (cambios pendientes, rango de commits o archivos) y las excepciones que el usuario aprobó durante la tarea.

## 8. Mensajes de commit

- Escribir siempre en inglés.
- Preferir el formato `[Main topic] Description`: un concepto principal breve entre corchetes, seguido de una descripción clara del cambio.
- Empezar la descripción con un verbo en imperativo: `Add`, `Configure`, `Fix`, `Update`, `Remove`.
- Describir el cambio concreto, sin frases genéricas como `Update code`.
- No agregar punto final.

Ejemplo:

```text
[Local HTTPS] Configure trusted SSL certificate and local domain app.nuvads.test
```
