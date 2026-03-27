# Chimera NoWP

> CMS Agentico 100% PHP Nativo para Hosting Compartido

## Descripcion

Chimera NoWP es un CMS (Content Management System) agentico construido enteramente en PHP nativo, sin dependencia de WordPress ni de frameworks pesados como Laravel o Symfony. Fusiona un motor de agente IA (Chimera) con un framework CMS completo (NoWP) en un solo proyecto, permitiendo que un agente de inteligencia artificial gestione, construya y opere un sitio web completo a traves de herramientas (tools) y conversacion natural.

El proyecto resuelve un problema concreto: la mayoria de los CMS modernos requieren servidores costosos, configuraciones complejas o dependen de ecosistemas cerrados. Chimera NoWP corre en cualquier hosting compartido barato con PHP 8.1+ y MySQL, sin necesidad de Node.js, Docker ni servicios externos obligatorios. El motor agentico soporta 4 proveedores LLM (incluyendo Ollama local y Cloudflare Workers AI gratuito), lo que permite operar un agente IA sin costos de API.

La arquitectura sigue el patron A2* (Agent-to-*): A2D (datos), A2E (ejecucion de workflows), A2I (integraciones externas), A2P (paginas/UI), A2T (testing), con un servidor MCP integrado que permite a Claude, Cursor y otros clientes MCP interactuar directamente con el CMS.

## Caracteristicas Principales

### Core Framework
- Contenedor de inyeccion de dependencias con autowiring y cache de reflexion (`Container`)
- Router HTTP con soporte para grupos, prefijos y middleware por ruta (`Router`)
- Pipeline de middleware (CSRF, Rate Limiting, Logging, Security Headers, Locale, Validation)
- Manejo centralizado de excepciones con modo debug (`ExceptionHandler`)
- Carga de configuracion desde archivos PHP y `.env`
- Service Providers para registro modular de servicios

### Gestion de Contenido
- CRUD completo con Content Types (`post`, `page`, `custom`)
- Content Status (`draft`, `published`, `scheduled`, `trash`)
- Custom Fields dinamicos por contenido
- Versionado automatico con historial y restauracion
- Generacion automatica de slugs con unicidad garantizada
- Soporte i18n con `locale` y `translation_group`

### Autenticacion y Autorizacion
- JWT (JSON Web Tokens) con `firebase/php-jwt`
- 4 roles: `admin`, `editor`, `author`, `subscriber`
- Sistema de permisos granular por rol (12 permisos)
- Middleware de autenticacion y permisos
- Proteccion por recurso (autores solo editan su contenido)

### Motor Agentico (Chimera)
- Agent Loop iterativo con anti-loop y limite de iteraciones (`AgentLoop`)
- 4 proveedores LLM: Ollama, Cloudflare Workers AI, OpenRouter, OpenAI
- Tool Registry con definiciones OpenAI-compatible (`ToolRegistry`)
- Bridges: CMS, Shell, Memory, Workflow, A2E
- Servidor MCP (Model Context Protocol) JSON-RPC 2.0 (`MCPServer`)
- Sistema de memoria con 5 colecciones (`MemoryService`)
- Scaffolding Engine para construccion conversacional de sistemas
- Gestion multi-proyecto con aislamiento de datos (`ProjectManager`)

### Busqueda Vectorial
- Busqueda semantica via `php-vector-store`
- Busqueda Matryoshka multi-stage (128, 256, 384 dims)
- Cuantizacion Int8 (392 bytes por vector)
- Busqueda hibrida: semantica + filtros por metadatos
- Auto-indexacion en create/update/delete de contenido
- Providers: Ollama, Cloudflare Workers AI, HTTP generico

### Cache
- Auto-deteccion del mejor adaptador disponible
- Adaptadores: APCu, Redis, Memcached, File, Null
- Patron `remember()` para cache-aside
- Invalidacion por tags y por clave

### Plugins
- Sistema de hooks estilo WordPress (actions y filters con prioridad)
- Lifecycle: `register()` -> `boot()` -> `deactivate()`
- Auto-descubrimiento: `glob('plugins/*')` carga y activa todo en `plugins/` al arrancar
- Resolucion de dependencias entre plugins
- Aislamiento de errores (un plugin roto no tumba el sistema)
- Registro de rutas desde plugins via `PluginManager::registerRoute()`
- Deploy remoto via tool `deploy_plugin` (MCP/API) sin acceso SSH ni reinicio

### Almacenamiento y Temas
- FileManager con validacion de MIME types y tamano
- ImageProcessor con generacion de thumbnails
- ThemeManager con herencia padre/hijo y rendering de templates

## Requisitos del Sistema

| Requisito | Minimo | Recomendado |
|-----------|--------|-------------|
| PHP | 8.1 | 8.2+ |
| MySQL | 5.7 | 8.0+ |
| Extensiones PHP | `pdo`, `json`, `mbstring` | + `apcu`, `gd`/`imagick` |
| Disco | 50 MB | 200 MB+ |
| RAM | 64 MB | 256 MB |

**Extensiones opcionales:**
- `apcu` -- Cache en memoria compartida (mejor rendimiento)
- `redis` -- Cache distribuido
- `gd` o `imagick` -- Procesamiento de imagenes y thumbnails
- `sqlite3` -- Sesiones del agente

## Instalacion Rapida

```bash
# 1. Clonar el repositorio
git clone https://github.com/mauricioperera/chimera-nowp.git
cd chimera-nowp

# 2. Instalar dependencias
composer install

# 3. Configurar entorno
cp .env.example .env
# Editar .env con credenciales de base de datos y JWT_SECRET

# 4. Ejecutar instalacion (crea BD, tablas y usuario admin)
php cli/install.php

# 5. Iniciar servidor de desarrollo
php -S localhost:8000 -t public/

# 6. (Opcional) Para el agente con Ollama local:
# Instalar Ollama -> ollama pull qwen2.5:7b
# Para embeddings Ollama: ollama pull embeddinggemma
#
# (Recomendado) Para embeddings con Cloudflare Workers AI (free tier):
# Configurar CF_ACCOUNT_ID y CF_API_TOKEN en .env
# SEARCH_PROVIDER=cloudflare -> @cf/google/embeddinggemma-300m automaticamente
```

Para produccion, apuntar el DocumentRoot del servidor web (Apache/Nginx) a la carpeta `public/`.

## Arquitectura

```
┌─────────────────────────────────────────────────────────────────┐
│                         HTTP Request                            │
│                      public/index.php                           │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                    ┌──────▼──────┐
                    │ Application │ (bootstrap, config, providers)
                    └──────┬──────┘
                           │
              ┌────────────▼────────────┐
              │     Middleware Pipeline  │
              │  CSRF - Auth - Rate -   │
              │  Locale - Security -    │
              │  Logging - Validation   │
              └────────────┬────────────┘
                           │
                    ┌──────▼──────┐
                    │    Router   │ (match + route groups)
                    └──────┬──────┘
                           │
         ┌─────────────────┼─────────────────┐
         │                 │                 │
   ┌─────▼─────┐   ┌──────▼──────┐   ┌─────▼─────┐
   │  Content   │   │   Agent     │   │  Search   │
   │ Controller │   │ Controller  │   │Controller │
   └─────┬─────┘   └──────┬──────┘   └─────┬─────┘
         │                │                 │
   ┌─────▼─────┐   ┌──────▼──────┐   ┌─────▼─────┐
   │  Content   │   │ AgentFacade │   │  Search   │
   │  Service   │   │  + Loop     │   │  Service  │
   └─────┬─────┘   └──────┬──────┘   └─────┬─────┘
         │                │                 │
         │          ┌─────┼─────┐           │
         │          │     │     │           │
         │     ┌────▼┐ ┌─▼──┐ ┌▼────┐      │
         │     │Tools│ │LLM │ │MCP  │      │
         │     │Reg. │ │Prov│ │Serv │      │
         │     └─────┘ └────┘ └─────┘      │
         │                                  │
   ┌─────▼──────────────────────────────────▼─────┐
   │              Database (Connection)            │
   │              QueryBuilder + PDO               │
   └──────────────────┬───────────────────────────┘
                      │
               ┌──────▼──────┐
               │    MySQL    │
               └─────────────┘
```

### Modulos

| Modulo | Namespace | Responsabilidad |
|--------|-----------|-----------------|
| **Core** | `ChimeraNoWP\Core` | Application, Router, Container, Request/Response, Middleware Pipeline, ExceptionHandler |
| **Auth** | `ChimeraNoWP\Auth` | JWT, Users, Roles, Permisos, AuthMiddleware, PermissionMiddleware |
| **Content** | `ChimeraNoWP\Content` | Content CRUD, ContentTypes, Versioning, Custom Fields, Media |
| **Database** | `ChimeraNoWP\Database` | Connection (PDO + retry), QueryBuilder, Migrations |
| **Cache** | `ChimeraNoWP\Cache` | CacheManager, Adaptadores (APCu, Redis, Memcached, File, Null) |
| **Plugin** | `ChimeraNoWP\Plugin` | HookSystem (actions/filters), PluginManager, PluginInterface |
| **Search** | `ChimeraNoWP\Search` | SearchService (vectorial), Embedding Providers, SearchController |
| **Agent** | `ChimeraNoWP\Agent` | AgentLoop, LLM Providers, Tools, MCP Server, Memory, Workflows |
| **Storage** | `ChimeraNoWP\Storage` | FileManager, ImageProcessor, StoredFile |
| **Theme** | `ChimeraNoWP\Theme` | ThemeManager (herencia padre/hijo, rendering) |
| **Install** | `ChimeraNoWP\Install` | InstallController, SystemRequirements |
| **Backup** | `ChimeraNoWP\Backup` | BackupCommand, RestoreCommand, MigrateCommand |

## API REST

### Contenido

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/contents` | Listar contenidos (filtros: type, status, author_id, limit, offset) | No |
| `GET` | `/api/contents/{id}` | Obtener contenido por ID | No |
| `GET` | `/api/contents/slug/{slug}` | Obtener contenido por slug | No |
| `POST` | `/api/contents` | Crear contenido | Si |
| `PUT` | `/api/contents/{id}` | Actualizar contenido | Si |
| `DELETE` | `/api/contents/{id}` | Eliminar contenido | Si |
| `GET` | `/api/contents/{id}/versions` | Historial de versiones | Si |
| `POST` | `/api/contents/{id}/versions/{versionId}/restore` | Restaurar version | Si |

### Media

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/media` | Listar archivos (page, per_page, mime_type) | No |
| `GET` | `/api/media/{id}` | Obtener archivo por ID | No |
| `POST` | `/api/media` | Subir archivo (multipart/form-data) | Si |
| `DELETE` | `/api/media/{id}` | Eliminar archivo | Si |

### Busqueda

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/search?q=texto&type=post&limit=10` | Busqueda semantica | No |
| `GET` | `/api/search/stats` | Estadisticas del indice | No |
| `POST` | `/api/search/reindex` | Reindexar todo el contenido | Si (admin) |

### Agente

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `POST` | `/api/agent/chat` | Chat con el agente | Si |
| `GET` | `/api/agent/tools` | Listar herramientas disponibles | No |
| `POST` | `/api/agent/tools/{name}` | Ejecutar herramienta directamente | Si |
| `POST` | `/api/agent/workflow` | Ejecutar workflow multi-paso | Si |
| `POST` | `/api/agent/memory` | Guardar memoria | Si |
| `GET` | `/api/agent/memory` | Consultar memoria | Si |
| `POST` | `/api/agent/reset` | Resetear sesion del agente | Si |
| `POST` | `/api/mcp` | MCP Server (JSON-RPC 2.0) | No |

### Entidades Dinamicas (A2D)

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/entities` | Listar schemas de entidades | No |
| `POST` | `/api/entities` | Definir nueva entidad (crea tabla + CRUD + search) | Si |
| `GET` | `/api/entities/{entity}` | Listar registros de una entidad | No |
| `POST` | `/api/entities/{entity}` | Insertar registro | Si |
| `GET` | `/api/entities/{entity}/{id}` | Obtener registro por ID | No |
| `PUT` | `/api/entities/{entity}/{id}` | Actualizar registro | Si |
| `DELETE` | `/api/entities/{entity}/{id}` | Eliminar registro | Si |

### Memoria del Agente

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/memory/stats` | Estadisticas de memoria | No |
| `POST` | `/api/memory/recall` | Recuperar memorias relevantes por query | Si |
| `POST` | `/api/memory/memories` | Guardar memoria (hechos, preferencias) | Si |
| `POST` | `/api/memory/skills` | Guardar skill (procedimientos aprendidos) | Si |
| `POST` | `/api/memory/knowledge` | Guardar conocimiento (documentacion) | Si |
| `POST` | `/api/memory/sessions` | Guardar resumen de sesion | Si |
| `POST` | `/api/memory/profiles` | Guardar perfil de usuario | Si |
| `GET` | `/api/memory/profiles/{agentId}/{userId}` | Obtener perfil | No |

### Servicios, Scheduler, Paginas y Proyectos

| Metodo | Ruta | Descripcion | Auth |
|--------|------|-------------|------|
| `GET` | `/api/services` | Listar integraciones externas | No |
| `POST` | `/api/services` | Integrar servicio REST externo | Si |
| `DELETE` | `/api/services/{name}` | Eliminar integracion | Si |
| `POST` | `/api/services/{name}/test` | Probar conectividad del servicio | Si |
| `GET` | `/api/schedules` | Listar workflows programados | No |
| `POST` | `/api/schedules` | Programar workflow autonomo | Si |
| `DELETE` | `/api/schedules/{id}` | Eliminar schedule | Si |
| `POST` | `/api/cron/tick` | Ejecutar tick del scheduler | Si |
| `GET` | `/api/pages` | Listar paginas definidas | No |
| `POST` | `/api/pages` | Definir pagina con template y componentes | Si |
| `GET` | `/api/pages/{slug}` | Renderizar pagina | No |
| `GET` | `/api/projects` | Listar proyectos | No |
| `POST` | `/api/projects` | Crear proyecto | Si |
| `POST` | `/api/projects/{id}/activate` | Activar proyecto (aisla datos) | Si |
| `POST` | `/api/scaffold` | Scaffolding conversacional | Si |
| `GET` | `/api/scaffold` | Estado del scaffolding | No |
| `GET` | `/api/builder/status` | Dashboard completo del builder | No |
| `POST` | `/api/agent/test` | Ejecutar suite de tests declarativa | Si |

## Sistema de Autenticacion

### Flujo JWT

```
1. Cliente envia POST /api/auth/login con email + password
2. Servidor valida credenciales y genera JWT (JWTManager::generateToken):
   - sub: user ID
   - email: correo del usuario
   - role: rol del usuario
   - iat: timestamp de emision
   - exp: timestamp de expiracion (configurable via JWT_EXPIRATION, default 3600s)
   Algoritmo: HS256
3. Cliente almacena el token
4. En cada request protegido, envia header:
   Authorization: Bearer <token>
5. AuthMiddleware extrae y valida el token, construye un objeto User
   y lo inyecta en el Request via setAttribute('user', $user)
6. PermissionMiddleware verifica que el usuario tenga el permiso requerido
```

### Roles y Permisos

La clase `UserRole` (enum PHP 8.1) define 4 roles con permisos explicitos:

| Permiso | Admin | Editor | Author | Subscriber |
|---------|-------|--------|--------|------------|
| `content.create` | Si | Si | Si | - |
| `content.read` | Si | Si | Si | Si |
| `content.update` | Si | Si | Solo propio | - |
| `content.delete` | Si | Si | - | - |
| `content.publish` | Si | Si | Si | - |
| `user.create` | Si | - | - | - |
| `user.read` | Si | - | - | - |
| `user.update` | Si | - | - | - |
| `user.delete` | Si | - | - | - |
| `plugin.manage` | Si | - | - | - |
| `settings.manage` | Si | - | - | - |
| `media.upload` | Si | Si | Si | - |
| `media.delete` | Si | Si | - | - |

El metodo `User::can($action, $resource)` permite verificaciones contextuales: un Author puede editar su propio contenido pero no el de otros.

## Sistema de Contenido

### Content Types

Definidos como enum `ContentType`:
- **`post`** -- Articulos y entradas de blog
- **`page`** -- Paginas estaticas del sitio
- **`custom`** -- Tipos personalizados definidos por plugins o por el agente

### Content Status

Definidos como enum `ContentStatus`:
- **`draft`** -- Borrador, no visible publicamente
- **`published`** -- Publicado y visible
- **`scheduled`** -- Programado para publicacion futura
- **`trash`** -- En papelera

### Custom Fields

Cada contenido puede tener campos personalizados almacenados como pares clave-valor en la tabla `custom_fields`. El `CustomFieldRepository` provee carga con eager loading (`getFieldsForMultipleContents`) para evitar queries N+1 en listados.

### Versionado

Cada operacion de `createContent` o `updateContent` genera automaticamente un snapshot en la tabla `content_versions` con titulo, slug, contenido, tipo, status y autor. Se puede consultar el historial con `getVersionHistory($contentId)` y restaurar cualquier version con `restoreVersion($contentId, $versionId)`.

### Internacionalizacion (i18n)

Cada contenido tiene un campo `locale` (default: `en`) y un `translation_group` opcional (UUID compartido) que agrupa las traducciones del mismo contenido en diferentes idiomas. El `LocaleMiddleware` detecta el idioma del request.

## Sistema de Cache

### Adaptadores Disponibles

| Adaptador | Clase | Requiere | Uso ideal |
|-----------|-------|----------|-----------|
| **APCu** | `APCuAdapter` | Extension `apcu` | Hosting con shared memory |
| **Redis** | `RedisAdapter` | Extension `redis` | VPS / Cloud con Redis |
| **Memcached** | `MemcachedAdapter` | Extension `memcached` | Clusters distribuidos |
| **File** | `FileAdapter` | Nada | Cualquier hosting (fallback) |
| **Null** | `NullCacheAdapter` | Nada | Testing / desarrollo |

### Configuracion

El `CacheManager` auto-detecta el mejor adaptador disponible en orden: APCu > Redis > Memcached > File. Se puede forzar un driver via `CACHE_DRIVER` en `.env` o en `config/cache.php`.

```php
// Cache-aside con TTL
$value = $cache->remember('content:42', 3600, fn() => $repo->find(42));

// Invalidacion por clave
$cache->invalidate('content:42');

// Invalidacion por tags
$cache->invalidate(['content']);
```

## Sistema de Plugins

### Hook System

Sistema de hooks estilo WordPress con dos tipos:

- **Actions** (`doAction`/`addAction`): Ejecutan callbacks sin modificar datos. Utiles para side effects (logging, notificaciones, indexacion).
- **Filters** (`applyFilters`/`addFilter`): Pasan un valor a traves de callbacks que pueden modificarlo y retornarlo.

Ambos soportan prioridad numerica (menor = se ejecuta primero, default: 10).

### Auto-carga de Plugins

`PluginManager` escanea `plugins/` al arrancar con `glob()`. Todo directorio que contenga un archivo `{nombre}/{nombre}.php` con una clase `Plugins\{Nombre}\{Nombre}` es cargado y activado automaticamente — sin registro manual.

**Convenciones de nombres:**

| Directorio | Archivo | Clase |
|-----------|---------|-------|
| `plugins/mi-plugin/` | `mi-plugin.php` | `Plugins\MiPlugin\MiPlugin` |
| `plugins/invoice-generator/` | `invoice-generator.php` | `Plugins\InvoiceGenerator\InvoiceGenerator` |

### Deploy via Agente o MCP

Claude Code u otro cliente MCP puede desplegar plugins sin acceso SSH usando la tool `deploy_plugin`:

```json
POST /api/agent/tools/deploy_plugin
{
  "name": "invoice-generator",
  "code": "<?php\ndeclare(strict_types=1);\nnamespace Plugins\\InvoiceGenerator;\n..."
}
```

Respuesta:
```json
{
  "plugin": "invoice-generator",
  "file": "/path/to/plugins/invoice-generator/invoice-generator.php",
  "activated": true,
  "errors": [],
  "note": "Plugin active. Routes registered on next request."
}
```

El plugin queda activo en el mismo request. Sus rutas son accesibles desde el siguiente request (cuando `Application::boot()` vuelve a ejecutar `loadPlugins()`).

### Como Crear un Plugin Manualmente

1. Crear directorio en `plugins/mi-plugin/`
2. Crear archivo `plugins/mi-plugin/mi-plugin.php`
3. Implementar `PluginInterface`:

```php
<?php
namespace Plugins\MiPlugin;

use ChimeraNoWP\Plugin\PluginInterface;
use ChimeraNoWP\Core\Container;
use ChimeraNoWP\Plugin\HookSystem;

class MiPlugin implements PluginInterface
{
    private HookSystem $hooks;

    public function __construct(Container $container, HookSystem $hooks)
    {
        $this->hooks = $hooks;
    }

    public function register(): void
    {
        // Registrar servicios en el container
    }

    public function boot(): void
    {
        $this->hooks->addFilter('content.before_create', function ($data) {
            // Modificar datos antes de crear contenido
            return $data;
        });

        $this->hooks->addAction('content.created', function ($content) {
            // Reaccionar a contenido creado
        });
    }

    public function deactivate(): void {}
    public function getName(): string { return 'Mi Plugin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getDependencies(): array { return []; }
}
```

### Hooks Disponibles en Core

| Hook | Tipo | Descripcion |
|------|------|-------------|
| `content.get` | Filter | Modificar contenido al leerlo |
| `content.before_create` | Filter | Modificar datos antes de crear |
| `content.before_update` | Filter | Modificar datos antes de actualizar |
| `content.can_delete` | Filter | Permitir/prevenir eliminacion (retorna bool) |
| `content.created` | Action | Despues de crear contenido |
| `content.updated` | Action | Despues de actualizar contenido |
| `content.deleted` | Action | Despues de eliminar contenido |
| `plugin.activated` | Action | Despues de activar un plugin |
| `plugin.deactivated` | Action | Despues de desactivar un plugin |
| `plugin.error` | Action | Cuando un plugin genera un error |

## Motor Agentico (A2*)

### Arquitectura del Agente

```
┌──────────────────────────────────────────┐
│              AgentFacade                  │
│  (punto de entrada unificado)            │
└──────────────┬───────────────────────────┘
               │
        ┌──────▼──────┐
        │  AgentLoop   │ (iteracion LLM + tools)
        │  + AntiLoop  │ (deteccion de bucles)
        └──────┬──────┘
               │
    ┌──────────┼──────────┐
    │          │          │
┌───▼───┐ ┌───▼───┐ ┌────▼────┐
│  LLM  │ │ Tool  │ │ Event   │
│Provider│ │Registry│ │ Emitter │
└───────┘ └───┬───┘ └─────────┘
              │
    ┌─────────┼─────────────┐
    │         │             │
┌───▼───┐ ┌──▼────┐ ┌──────▼──────┐
│ CMS   │ │Shell  │ │ Memory /    │
│Bridge │ │Bridge │ │ Workflow /  │
│       │ │       │ │ A2E Bridge  │
└───────┘ └───────┘ └─────────────┘
```

El `AgentLoop` (`src/Agent/Core/AgentLoop.php`) sigue este ciclo:

1. Enviar mensajes + definiciones de tools al LLM
2. Si el LLM pide tool calls -> ejecutar via `ToolRegistry` -> agregar resultados -> volver a 1
3. Si el LLM responde texto -> retornar respuesta final
4. **Anti-loop**: si se detectan los mismos tool calls repetidos -> deshabilitar tools -> forzar respuesta de texto
5. **Limite maximo**: default 25 iteraciones (configurable via `AGENT_MAX_ITERATIONS`)

### Providers LLM Soportados

| Provider | Clase | Costo | Modelo Default |
|----------|-------|-------|----------------|
| **Ollama** | `OllamaProvider` | Gratuito (local) | `qwen2.5:7b` |
| **Cloudflare Workers AI** | `WorkersAIProvider` | Free tier generoso | `@cf/ibm-granite/granite-4.0-h-micro` |
| **OpenRouter** | `OpenRouterProvider` | Por uso (200+ modelos) | `nousresearch/hermes-4-scout` |
| **OpenAI** | `OpenAIProvider` | Por uso | `gpt-4o-mini` |

Todos implementan `ProviderInterface` (`src/Agent/LLM/ProviderInterface.php`):

```php
interface ProviderInterface
{
    public function name(): string;
    public function model(): string;
    public function chat(array $messages, array $tools = []): LLMResponse;
    public function setModel(string $model): void;
}
```

### Tools Disponibles

Las herramientas se registran como `ToolDefinition` en el `ToolRegistry` y se exponen automaticamente al LLM y al servidor MCP.

| Categoria | Tool | Descripcion |
|-----------|------|-------------|
| **CMS** | `search_content` | Busqueda semantica de contenido |
| **CMS** | `get_content` | Obtener contenido por ID |
| **CMS** | `list_content` | Listar contenidos con filtros |
| **CMS** | `create_content` | Crear contenido nuevo (post, page, custom) |
| **CMS** | `update_content` | Actualizar contenido existente |
| **Entity** | `define_entity` | Crear entidad: tabla MySQL + CRUD + validacion + search |
| **Entity** | `list_entities` | Listar schemas de entidades definidas |
| **Entity** | `entity_insert` | Insertar registro en entidad |
| **Entity** | `entity_find` | Buscar registro por ID |
| **Entity** | `entity_list` | Listar registros con filtros |
| **Entity** | `entity_update` | Actualizar registro |
| **Entity** | `entity_delete` | Eliminar registro |
| **Entity** | `entity_search` | Busqueda semantica dentro de una entidad |
| **Memory** | (via MemoryBridge) | Guardar y recuperar memorias, skills, conocimiento |
| **Workflow** | (via WorkflowBridge) | Ejecutar workflows multi-paso |
| **Scheduler** | `schedule_workflow` | Programar ejecucion autonoma con intervalo |
| **Scheduler** | `list_schedules` | Listar workflows programados |
| **Scheduler** | `unschedule_workflow` | Eliminar schedule |
| **Integration** | `integrate_service` | Conectar API REST externa como tool |
| **Integration** | `list_services` | Listar servicios integrados |
| **Integration** | `remove_service` | Eliminar integracion |
| **Page** | `define_page` | Definir pagina UI con template y componentes |
| **Page** | `list_pages` | Listar paginas definidas |
| **Page** | `get_component_catalog` | Ver componentes UI disponibles |
| **Plugin** | `deploy_plugin` | Escribir plugin PHP en `plugins/{name}/` y activarlo (rutas activas en siguiente request) |
| **Plugin** | `list_plugins` | Listar plugins cargados con estado activo/inactivo y errores |
| **Plugin** | `deactivate_plugin` | Desactivar un plugin activo |
| **Testing** | `run_tests` | Ejecutar suite de tests declarativa |
| **Shell** | `cli_help` | Obtener protocolo de interaccion del shell |
| **Shell** | `cli_exec` | Ejecutar comando CLI (requiere php-agent-shell) |

### MCP Server

El servidor MCP (`src/Agent/MCP/MCPServer.php`) expone todas las herramientas del agente a clientes como Claude Desktop, Cursor, Windsurf, y cualquier cliente compatible con el Model Context Protocol.

**Endpoint:** `POST /api/mcp`

**Protocolo:** JSON-RPC 2.0 sobre HTTP (Streamable HTTP transport)

**Metodos soportados:**

| Metodo | Descripcion |
|--------|-------------|
| `initialize` | Retorna info del servidor, version del protocolo y capabilities |
| `tools/list` | Lista todas las herramientas con nombre, descripcion e `inputSchema` |
| `tools/call` | Ejecuta una herramienta por nombre con argumentos |
| `ping` | Health check |

**Configuracion en Claude Desktop** (`claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "mi-sitio": {
      "url": "https://mi-sitio.com/api/mcp"
    }
  }
}
```

### Subsistemas A2*

| Subsistema | Componentes | Descripcion |
|------------|-------------|-------------|
| **A2D** (Agent-to-Data) | `EntitySchema`, `EntityMaterializer` | Define entidades dinamicas: crea tabla MySQL, genera CRUD API, validacion y busqueda vectorial automaticamente |
| **A2E** (Agent-to-Execute) | `WorkflowEngine`, `DataStore` | Workflows multi-paso con operaciones: ExecuteTool, FilterData, TransformData, Conditional, Loop, StoreData, Wait, MergeData |
| **A2I** (Agent-to-Integrate) | `IntegrationManager`, `ServiceDefinition` | Conecta APIs REST externas como herramientas del agente con autenticacion configurable |
| **A2P** (Agent-to-Page) | `PageBuilder`, `ComponentCatalog` | Define paginas UI con templates, layouts, secciones y componentes |
| **A2T** (Agent-to-Test) | `TestRunner` | Ejecuta suites de tests declarativas contra entidades, workflows y paginas |

### Memoria del Agente

El `MemoryService` (`src/Agent/Memory/MemoryService.php`) gestiona 5 colecciones de memoria persistente con busqueda vectorial:

| Coleccion | Descripcion |
|-----------|-------------|
| **memories** | Hechos, preferencias y correcciones del usuario |
| **skills** | Procedimientos y patrones aprendidos por el agente |
| **knowledge** | Documentacion y conocimiento de dominio |
| **sessions** | Resumenes de conversaciones anteriores |
| **profiles** | Perfiles de usuarios individuales |

El `SessionStore` usa SQLite para persistir el historial de conversacion. El `ContextBuilder` construye el contexto de memoria relevante para cada interaccion.

## Busqueda Vectorial

### Embedding Providers

| Provider | Clase | Modelo | Requisito |
|----------|-------|--------|-----------|
| **Cloudflare** (recomendado) | `HttpEmbeddingProvider` | `@cf/google/embeddinggemma-300m` | Cuenta CF Workers AI (free tier) |
| Ollama | `OllamaEmbeddingProvider` | `embeddinggemma` | Ollama local |
| OpenAI | `HttpEmbeddingProvider` | `text-embedding-3-small` | API key OpenAI |
| HTTP generico | `HttpEmbeddingProvider` | cualquiera | Cualquier API compatible |

Todos implementan `EmbeddingProviderInterface`:

```php
interface EmbeddingProviderInterface
{
    public function embed(string $text): array;  // retorna float[]
    public function dimensions(): int;
}
```

### Como Funciona

```
Contenido guardado -> Hook fires -> SearchService::indexContent()
    -> Genera embedding de titulo + cuerpo + custom fields
    -> Vector cuantizado Int8 almacenado en QuantizedStore (392 bytes)

Query de busqueda -> SearchService::search()
    -> Genera embedding de la query
    -> Matryoshka multi-stage:
        Stage 1: 128 dims (filtrado rapido)
        Stage 2: 256 dims (refinamiento)
        Stage 3: 384 dims (ranking final)
    -> Resultados ordenados por similitud coseno
```

`hybridSearch()` combina resultados semanticos con filtros por metadatos (ej: `status=published`), solicitando 3x mas candidatos para compensar el filtrado.

**Configuracion en `.env`:**

```bash
# CF EmbeddingGemma — mismas credenciales CF_ACCOUNT_ID / CF_API_TOKEN del agente
SEARCH_PROVIDER=cloudflare
CF_EMBED_MODEL=@cf/google/embeddinggemma-300m
CF_EMBED_DIMS=768          # dimensiones full del modelo
SEARCH_DIMENSIONS=384      # Matryoshka truncation -> stages [128, 256, 384]

# Alternativa Ollama local:
# SEARCH_PROVIDER=ollama
# OLLAMA_EMBED_MODEL=embeddinggemma
```

## CLI

### Comandos Disponibles

```bash
# Instalacion completa (crea BD, ejecuta migraciones, crea usuario admin)
php cli/install.php

# Ejecutar cron (procesa workflows programados)
php cli/cron.php

# CLI interactivo del agente
php cli/chimera.php
```

### Cron

Para que los workflows autonomos se ejecuten segun su schedule, agregar al crontab del sistema:

```bash
* * * * * php /ruta/a/chimera-nowp/cli/cron.php >> /ruta/a/storage/logs/cron.log 2>&1
```

El scheduler tambien se ejecuta automaticamente via `register_shutdown_function` en cada request HTTP como fallback.

## Testing

```bash
# Ejecutar todos los tests
composer test

# O directamente con Pest
./vendor/bin/pest

# Con reporte de cobertura
composer test:coverage
```

El proyecto usa **Pest** (basado en PHPUnit) con tests unitarios e integracion:

```
tests/
├── Unit/
│   ├── Auth/           # JWT, User, Roles, AuthMiddleware, PermissionMiddleware
│   ├── Cache/          # APCu, File, CacheManager, integracion Content+Cache
│   ├── Content/        # ContentController, ContentService, Repository, Media
│   ├── Core/           # Router, Container, Middleware, OpenAPI, RateLimiter
│   ├── Database/       # Connection, QueryBuilder, Migrations de cada tabla
│   ├── Install/        # SystemRequirements
│   ├── Plugin/         # PluginManager, ExamplePlugin
│   ├── Storage/        # FileManager, ImageProcessor, orphan detection
│   └── Theme/          # ThemeManager, helpers
├── Integration/        # AuthMiddleware, ExceptionHandling, Migrations
└── Properties/         # Property-based tests (Database)
```

## Configuracion

### Variables de Entorno (.env)

```bash
# ── Base de datos ──────────────────────────
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=chimera_nowp
DB_USERNAME=root
DB_PASSWORD=

# ── Aplicacion ─────────────────────────────
APP_NAME="Chimera NoWP"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

# ── Autenticacion JWT ──────────────────────
JWT_SECRET=tu-clave-secreta-aleatoria
JWT_EXPIRATION=3600

# ── Cache ──────────────────────────────────
CACHE_DRIVER=auto                    # auto | apcu | redis | memcached | file
CACHE_PREFIX=chimera_

# ── Upload de archivos ─────────────────────
UPLOAD_MAX_SIZE=10485760             # 10 MB
UPLOAD_ALLOWED_TYPES=jpg,jpeg,png,gif,pdf,doc,docx

# ── Locale ─────────────────────────────────
DEFAULT_LOCALE=en
SUPPORTED_LOCALES=en,es,fr

# ── Rate Limiting ──────────────────────────
RATE_LIMIT_MAX_ATTEMPTS=60
RATE_LIMIT_DECAY_SECONDS=60

# ── Motor Agentico ─────────────────────────
AGENT_ENABLED=true
AGENT_PROVIDER=ollama                # ollama | cloudflare | openrouter | openai
AGENT_MAX_ITERATIONS=25

# ── Ollama (local, gratuito) ───────────────
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b

# ── Cloudflare Workers AI (cloud, free tier)
CF_ACCOUNT_ID=
CF_API_TOKEN=
CF_AI_MODEL=@cf/ibm-granite/granite-4.0-h-micro

# ── OpenRouter (200+ modelos) ──────────────
OPENROUTER_API_KEY=
OPENROUTER_MODEL=nousresearch/hermes-4-scout

# ── OpenAI ─────────────────────────────────
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini

# ── Busqueda Vectorial ─────────────────────
SEARCH_PROVIDER=cloudflare           # cloudflare | ollama | openai | custom
CF_EMBED_MODEL=@cf/google/embeddinggemma-300m
CF_EMBED_DIMS=768                    # dims full del modelo (768)
SEARCH_DIMENSIONS=384                # Matryoshka: 3 etapas [128, 256, 384]
```

### Archivos de Configuracion

| Archivo | Descripcion |
|---------|-------------|
| `config/agent.php` | Motor agentico: provider, modelos, memory, paths de subsistemas A2* |
| `config/app.php` | Configuracion general: nombre, entorno, debug, JWT, seguridad |
| `config/cache.php` | Drivers de cache, orden de deteccion, configuracion por driver |
| `config/database.php` | Conexiones de base de datos (MySQL/SQLite), retry, charset |

## Estructura de Directorios

```
chimera-nowp/
├── cli/                            # Scripts de linea de comandos
│   ├── chimera.php                 #   CLI interactivo del agente
│   ├── cron.php                    #   Runner de cron para workflows programados
│   └── install.php                 #   Instalador (BD + migraciones + admin)
├── config/                         # Archivos de configuracion PHP
│   └── agent.php                   #   Config del motor agentico
├── migrations/                     # Migraciones de base de datos
│   ├── ..._create_users_table.php
│   ├── ..._create_contents_table.php
│   ├── ..._create_media_table.php
│   ├── ..._create_custom_fields_table.php
│   ├── ..._create_content_versions_table.php
│   └── ..._add_locale_to_contents.php
├── plugins/                        # Plugins del CMS
│   └── example-plugin/
├── public/                         # Document root del servidor web
│   └── index.php                   #   Entry point HTTP
├── src/
│   ├── Agent/                      # Motor agentico Chimera
│   │   ├── Bridge/                 #   CMSBridge, ShellBridge, MemoryBridge,
│   │   │                           #   WorkflowBridge, A2EBridge
│   │   ├── Core/                   #   AgentLoop, AntiLoop, ToolRegistry,
│   │   │                           #   ToolDefinition, EventEmitter
│   │   ├── Data/                   #   A2D: EntitySchema, EntityMaterializer
│   │   ├── Gateway/                #   CliGateway
│   │   ├── Integration/            #   A2I: IntegrationManager, ServiceDefinition
│   │   ├── LLM/                    #   OllamaProvider, WorkersAIProvider,
│   │   │                           #   OpenRouterProvider, OpenAIProvider,
│   │   │                           #   ProviderInterface, LLMResponse, Message
│   │   ├── MCP/                    #   MCPServer, MCPController (JSON-RPC 2.0)
│   │   ├── Memory/                 #   MemoryService, SessionStore, ContextBuilder,
│   │   │                           #   ConsolidationPipeline, MiningPipeline, LearningLoop
│   │   ├── Page/                   #   A2P: PageBuilder, ComponentCatalog
│   │   ├── Project/                #   ProjectManager (multi-proyecto)
│   │   ├── Scaffolding/            #   ScaffoldingEngine (construccion conversacional)
│   │   ├── Testing/                #   A2T: TestRunner
│   │   └── Workflow/               #   WorkflowEngine, Scheduler, DataStore
│   ├── Auth/                       # Autenticacion y autorizacion
│   │   ├── AuthMiddleware.php      #   Validacion JWT + inyeccion de User
│   │   ├── JWTManager.php          #   Generacion y validacion de tokens
│   │   ├── PasswordHasher.php      #   Bcrypt hashing
│   │   ├── PermissionMiddleware.php#   Verificacion de permisos por rol
│   │   ├── User.php                #   Modelo de usuario con can()
│   │   └── UserRole.php            #   Enum de roles con permisos
│   ├── Backup/                     # Backup y restauracion
│   ├── Cache/                      # CacheManager y 5 adaptadores
│   ├── Content/                    # Content, ContentController, ContentService,
│   │                               # ContentRepository, Media, CustomFields
│   ├── Core/                       # Application, Router, Container, Request,
│   │   │                           # Response, Route, ExceptionHandler
│   │   └── Middleware/             # CSRF, Locale, Logging, RateLimit,
│   │                               # SecurityHeaders, Validation
│   ├── Database/                   # Connection, QueryBuilder, Migration, MigrationRunner
│   ├── Install/                    # InstallController, SystemRequirements
│   ├── Plugin/                     # HookSystem, PluginManager, PluginInterface
│   ├── Search/                     # SearchService, SearchController, SearchServiceProvider
│   │                               # EmbeddingProviderInterface, Ollama/Http providers
│   ├── Storage/                    # FileManager, ImageProcessor, StoredFile
│   └── Theme/                      # ThemeManager
├── storage/                        # Datos de runtime (no versionado)
│   ├── agent/                      #   Memoria, sesiones, schedules, paginas, integraciones
│   ├── cache/                      #   Cache de archivos
│   └── logs/                       #   Logs de la aplicacion
├── tests/                          # Tests con Pest/PHPUnit
├── composer.json
├── .env.example
└── README.md
```

## Seguridad

### Medidas Implementadas

- **SQL Injection**: Todos los queries usan prepared statements via PDO. El `QueryBuilder` valida operadores contra whitelist (`=`, `!=`, `<`, `>`, `LIKE`, `IN`, etc.). El `ContentController` valida `order_by` contra columnas permitidas.
- **XSS**: `SecurityHeadersMiddleware` agrega `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`.
- **CSRF**: `CSRFMiddleware` con tokens por sesion para requests que modifican estado.
- **Rate Limiting**: `RateLimitMiddleware` configurable por endpoint (default: 60 requests/minuto).
- **JWT**: Tokens firmados con HMAC-SHA256 via `firebase/php-jwt`, con expiracion configurable.
- **Password Hashing**: Bcrypt via `PasswordHasher` (`password_hash` / `password_verify`).
- **File Upload**: Validacion de MIME types contra whitelist, tamano maximo configurable, generacion de nombres unicos con hash.
- **Shell Commands**: El fallback shell del agente solo permite comandos de whitelist (`ls`, `cat`, `grep`, `find`, `php`, `composer`). Paths de archivos validados contra `BASE_PATH` para prevenir path traversal.
- **Database Charset**: Validacion de charset contra whitelist, sanitizacion de collation con regex.
- **Connection Retry**: 3 reintentos automaticos para conexiones transientes con delay configurable.
- **Plugin Isolation**: Errores de plugins se capturan con `try/catch` sin afectar el core.
- **Logging**: `SecurityLogger` para auditar eventos de seguridad.

## Paquetes Opcionales

| Paquete | Descripcion |
|---------|-------------|
| `mauricioperera/php-agent-memory` | Memoria persistente mejorada con deduplicacion vectorial |
| `mauricioperera/php-agent-shell` | Descubrimiento de comandos CLI para el agente (patron Agent Shell) |
| `mauricioperera/php-a2e` | Motor de automatizacion de workflows A2E |

## Rendimiento

| Metrica | Valor |
|---------|-------|
| Memoria tipica | ~4 MB |
| Tiempo de respuesta | <100 ms |
| Tamano en disco | <100 MB (core) |
| Almacenamiento por vector | 392 bytes (Int8, 384 dims) |
| Busqueda semantica | ~5 ms por query |
| Hosting minimo | $3/mes (shared) |

## Licencia

MIT License
