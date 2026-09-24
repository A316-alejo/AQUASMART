# AquaSmart — reglas del proyecto

## Proyecto

AquaSmart: plataforma web para gestión y monitoreo de piscicultura (principalmente tilapia), orientada a pequeños y grandes productores. Interfaz y respuestas en español.

## Stack

- Laravel 12, PHP 8.2 (XAMPP)
- MySQL/MariaDB (XAMPP)
- Blade + Eloquent
- JavaScript y CSS propios compilados con Vite (`resources/css/aquasmart.css`, `resources/js/charts.js`)
- Sin paquetes innecesarios: no instalar dependencias sin justificarlo y pedir aprobación.

## Diseño

- Respetar el diseño visual existente de AquaSmart (colores, tipografías, tarjetas, tablas, formularios, navegación).
- No usar plantillas administrativas genéricas, Bootstrap, Breeze, Fortify, Tailwind ni Chart.js.
- Reutilizar componentes existentes: `x-form.input`, `x-form.select`, `x-user-status`, `x-role-tag`, `x-icon`, `x-brand`, paginación `partials.pagination`.
- Mantener responsive y modo oscuro.
- Estilos nuevos van en la sección "Complementos" de `aquasmart.css`.

## Arquitectura

Mantener separación entre Models, Controllers, Form Requests, Policies, Gates, Services, Views y Components.

- Sin lógica de negocio compleja en Blade ni consultas a la base desde las vistas.
- Evitar N+1 con eager loading (seleccionando solo las columnas necesarias).
- `Model::shouldBeStrict()` está activo fuera de producción: lazy loading o atributos faltantes lanzan excepción.
- Visibilidad por usuario mediante scopes `visibleTo($user)` (atributo `#[Scope]`); el método `isVisibleTo()` del modelo reutiliza el scope para que listado y Policy compartan la misma regla.
- Normalización de entrada en `prepareForValidation` (coma decimal → punto, códigos en mayúscula, `Str::squish`).
- Los parámetros de ruta de modelos son solo numéricos (`Route::pattern` en `AppServiceProvider`).

## Seguridad

- La autorización siempre se valida en backend; ocultar botones (`@can`) es solo complemento.
- Gates para permisos generales (`manage-users`, `assign-role`, `manage-producers`, `manage-farms`, `manage-ponds`, …).
- Policies para autorización sobre recursos; middleware `can:` en rutas cuando corresponda.
- Nunca confiar en el ID de la URL: un recurso ajeno responde 403.
- Contraseñas siempre con hash. Tokens de dispositivos nunca en texto plano (hash).
- No eliminar físicamente información productiva: usar `is_active` (activar/desactivar) y claves foráneas con `restrictOnDelete`. Sin rutas DELETE para esos recursos.
- Usuarios o productores inactivos no pueden ingresar (middleware `active`).

## Roles

Columna `role` en `users` (enum `App\Enums\Role`): `admin`, `productor`, `tecnico`, `consulta`.

- **admin**: administra todo.
- **productor**: solo los recursos de su productor (`users.producer_id`).
- **tecnico**: fincas asignadas en `farm_user` (y del mismo productor si tiene uno).
- **consulta**: solo lectura según las mismas asignaciones.
- Nadie puede otorgarse a sí mismo ni asignar roles superiores al propio.
- Los recursos hijos heredan la visibilidad de su finca (`whereHas('farm', visibleTo)`).

## Arquitectura de datos

Productor → Finca → Estanque → Lote/Ciclo de producción → Registros productivos → Calidad del agua → Alertas → Dispositivos/Sensores → Lecturas

- Un productor tiene múltiples fincas; una finca, múltiples estanques.
- Técnicos y usuarios de consulta pueden asignarse a múltiples fincas.
- Tablas separadas para alimentación, mortalidad y biometría; catálogo de métricas; la biomasa se calcula, no se fija.
- Tipos flexibles como enums respaldados por columnas string (ej. `PondType`), para ampliar sin migraciones.

## IoT (futuro)

ESP32/Sensores → Gateway/Red → API de AquaSmart → Laravel → MySQL → Evaluación de umbrales → Alertas → Dashboard

- Sensores previstos: temperatura, pH, oxígeno disuelto, nivel de agua, amoníaco y otros por definir.
- La API debe autenticar el dispositivo (token con hash) y validar todos los datos recibidos, incluida la codificación (UTF-8).
- No implementar conexión física con sensores hasta que se solicite.

## Desarrollo

Orden oficial:

0. Preparación ✔
1. Autenticación ✔
2. Usuarios, roles, Gates y Policies ✔
3. Productores ✔
4. Fincas ✔
5. Estanques ✔
6. Catálogos y especies ✔
7. Lotes/ciclos de producción
8. Alimentación, mortalidad, biometría y biomasa
9. Calidad del agua y mediciones
10. Centro de alertas
11. Dashboard
12. IoT, dispositivos, sensores y API
13. Actualización periódica/tiempo real
14. Estadísticas y reportes
15. Asistente IA

No avanzar automáticamente al siguiente paso sin autorización.

## Base de datos

- Revisar la estructura existente antes de crear migraciones; no duplicar tablas ni modificar tablas existentes sin necesidad.
- Base de trabajo: `aquasmart`. Base de pruebas: `aquasmart_testing` (forzada en `phpunit.xml`).
- No borrar ni alterar datos de desarrollo/producción sin autorización.
- Cliente MySQL: `C:/xampp/mysql/bin/mysql.exe -u root --default-character-set=utf8mb4`.

## Pruebas

Después de cambios importantes:

- Ejecutar toda la suite: `php artisan test` (MySQL/MariaDB, `RefreshDatabase`).
- Crear pruebas Feature para lo nuevo en `tests/Feature/<Módulo>/`.
- Comprobar autorización por rol, validaciones en español, acceso con IDs ajenos (403), N+1 (conteo de consultas), login y dashboard.
- Un módulo no está terminado si alguna prueba existente falla.

## Convenciones

- Código limpio, nombres descriptivos, relaciones con Eloquent.
- Validación en Form Requests (reglas compartidas en traits `Validates…Input`); autorización con Policies.
- Migraciones reversibles.
- Mensajes de validación en español (`lang/es`).
- Zona horaria `America/Bogota`; interfaz en español; URLs en español (`/productores`, `/fincas`, `/estanques`) con nombres de ruta en inglés (`farms.show`).

## Git

- El repositorio es `C:\Users\agame\AquaSmart`. Nunca modificar el repositorio de `C:\Users\agame`.
- Un commit descriptivo (en español) por módulo; no hacer push sin autorización.

## Forma de trabajo con Claude Code

1. Revisar solo los archivos relevantes.
2. Explicar brevemente qué se modificará.
3. Implementar únicamente el módulo solicitado.
4. Ejecutar pruebas.
5. Verificar que los módulos anteriores siguen funcionando.
6. Crear un commit descriptivo.
7. Informar archivos, pruebas, resultado y commit.
8. Detenerse y esperar autorización.

No rehacer trabajo ya implementado ni repetir explicaciones extensas del proyecto.
