---
name: capas-backend
description: "Convenciones de Nuvads para las capas del backend: services, repositories y helpers — responsabilidades, acceso entre capas, inyección con resolve(), duración scoped y nombres de métodos. Cargar siempre antes de crear o modificar un service, repository o helper, o al decidir en qué capa va una lógica."
---

# Capas del backend de Nuvads

Estas convenciones aplican junto con las reglas generales de AGENTS.md (legibilidad, espaciado, nomenclatura).

## 1. Capas: acceso y reglas comunes

Capas:
- Service: punto de entrada a las operaciones y los datos de un dominio. Aplica las reglas de negocio.
- Repository: consultas y persistencia de un dominio en la base de datos del sistema.
- Helper: tareas técnicas auxiliares o comunicación con un sistema externo.

Acceso entre capas:
- Controllers, jobs, comandos y demás consumidores acceden a las operaciones de negocio y a los datos mediante services. No usan repositories ni modelos directamente para consultar o persistir.
- El acceso a integraciones externas también pasa por un service, que usa el helper correspondiente.
- Un service accede a otro dominio a través del service de ese dominio, no de su repository.
- Las excepciones a esta estructura requieren una decisión explícita del usuario.

Ejemplo de acceso a otro dominio: `LeadService -> TagService -> TagRepository`

Común a las tres capas:
- Ninguna obtiene datos desde `request()`.
- Ninguna construye respuestas HTTP de la API. Eso corresponde al controller y al handler de errores.
- Services y helpers se obtienen con `resolve()` dentro del método que los necesita, cerca de su uso. La única inyección por constructor es la del repository propio en su service.

Duración de las instancias:
- Services, repositories y helpers se registran como scoped por defecto.
- Dentro del mismo request o job, `resolve()` reutiliza la instancia. Al comenzar el siguiente, Laravel descarta las anteriores.
- Usar singleton u otra duración requiere una decisión explícita.

## 2. Services

Responsabilidades:
- Es el punto de entrada a las operaciones y los datos de su dominio.
- Ejecuta las operaciones, aplica las reglas de negocio y coordina los pasos necesarios.
- Consulta y persiste los datos de su dominio siempre mediante su repository. Puede tener métodos que simplemente deleguen al repository, para mantener una única vía de entrada al dominio.
- Recibe su repository por inyección en el constructor, cuando ese repository exista. Es la única inyección por constructor que se usa. Todo lo demás (otros services, helpers) se obtiene con `resolve()` dentro del método.
- Recibe explícitamente los datos necesarios para trabajar, incluido el cliente o usuario cuando corresponda.
- Decide cuándo usar una integración externa y qué hacer con su resultado. La comunicación con el proveedor la hace un helper.
- Delimita la transacción de la operación cuando corresponde. Los repositories ejecutan sus consultas y escrituras dentro de ella.

No debe:
- Construir consultas (`Model::where()`, `DB::table()`, etc.) ni persistir (`save()`, `update()`, `create()`). Eso corresponde al repository. Sí está permitido leer atributos y relaciones de modelos ya obtenidos (`$order->status`, `$order->items`), aunque una relación no cargada dispare una consulta. `DB::transaction()` también está permitido.
- Usar repositories de otros dominios.

Nombres de los métodos: los mismos verbos que los controllers: `create`, `update`, `delete`, `find`, `list`.

Para los métodos de búsqueda, el nombre dice qué devuelven. Si no dice nada, devuelve el modelo del propio service:
- `find(id)`: un modelo por id.
- `findOneBy...`: un solo modelo según el criterio. Por ejemplo, `TagService::findOneByEmail` devuelve un tag.
- `findBy...`: una collection de modelos según el criterio. Por ejemplo, `TagService::findByEmail` devuelve todos los tags con ese email.
- `countBy...`: un entero resultado de una cuenta. Por ejemplo, `countByStatus`.
- `get...`: algo que no es el modelo del service. El nombre dice qué devuelve. Por ejemplo, `TagService::getAppliedDateByName` devuelve una fecha, y `TagService::getTagCategoriesByTagAndEmail` devuelve categorías. `getByEmail` a secas no existe.

Ejemplo de integración externa — DocumentService:
1. Comprueba si corresponde importar el documento.
2. Pide a GoogleDriveHelper que lo descargue.
3. Prepara los datos que necesita el sistema.
4. Los guarda mediante DocumentRepository.

## 3. Repositories

Responsabilidades:
- Concentra las consultas y la persistencia de los datos de su dominio en la base de datos del sistema.
- Implementa búsquedas, filtros, relaciones, paginación, creación, actualización y eliminación.
- Usa los modelos y las herramientas de acceso a la base de datos.
- Ejecuta las operaciones de persistencia que decide el service.

No debe:
- Decidir reglas de negocio. Determinar si se permite cancelar un pedido corresponde al service; persistir la cancelación, al repository.
- Coordinar operaciones de negocio ni llamar a services.
- Enviar notificaciones ni hacer integraciones HTTP.

Nombres de los métodos: los mismos que los services: `create`, `update`, `delete`, `find`, `list`, `findOneBy...`, `findBy...`, `countBy...`, `get...`

## 4. Helpers

Responsabilidades:
- Resuelve tareas técnicas auxiliares, con una responsabilidad concreta.
- Puede agrupar utilidades de transformación o cálculo, como quitar saltos de línea de un texto o transformar un formato.
- Puede encapsular la comunicación con un sistema externo. En ese caso conoce los endpoints, la autenticación y los formatos del proveedor: construye las solicitudes, hace las llamadas y valida las respuestas.
- Puede consultar o modificar datos del proveedor externo (descargar un archivo, subirlo, enviar un mensaje). Eso no lo convierte en un repository de la aplicación.
- Recibe los datos necesarios y devuelve un resultado consistente o comunica el error.

No debe:
- Consultar ni modificar la base de datos del sistema.
- Aplicar reglas de negocio ni coordinar operaciones de negocio.
- Llamar a services o repositories.
- Decidir qué acciones de negocio corresponden después de una llamada al proveedor externo.
- Agrupar funcionalidades sin relación entre sí solo porque sean auxiliares.

Ejemplo: GoogleDriveHelper conoce la comunicación con Google y valida su respuesta. La decisión de importar y registrar el documento pertenece a DocumentService.
