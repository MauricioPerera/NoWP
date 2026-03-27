# Chimera NoWP - Analisis Completo de Codigo

**Fecha:** 2026-03-26
**Archivos analizados:** 118 (19,715 LOC)
**Modulos:** 12

---

## RESUMEN EJECUTIVO

| Categoria              | Hallazgos Criticos | Altos | Medios | Bajos |
|------------------------|--------------------|-------|--------|-------|
| Seguridad              | 6                  | 7     | 5      | 4     |
| Bugs funcionales       | 8                  | 6     | 5      | 3     |
| Rendimiento            | 0                  | 3     | 5      | 2     |
| Calidad de codigo      | 0                  | 2     | 4      | 8     |
| **Total**              | **14**             | **18**| **19** | **17**|

---

## 1. VULNERABILIDADES DE SEGURIDAD

### 1.1 CRITICAS

#### SEC-01: Ejecucion de shell sin restricciones
- **Archivo:** `src/Agent/Bridge/ShellBridge.php:33`
- **Problema:** `exec($args['cmd'])` pasa comandos del LLM directamente a `exec()` sin sanitizacion
- **Impacto:** Ejecucion remota de codigo (RCE) si el LLM es manipulado via prompt injection
- **Fix:** Implementar whitelist de comandos + `escapeshellarg()` + sandbox

#### SEC-02: SSL deshabilitado en 3 de 4 proveedores LLM
- **Archivos:** `OpenAIProvider.php:48`, `OpenRouterProvider.php:48`, `WorkersAIProvider.php:43`
- **Problema:** `CURLOPT_SSL_VERIFYPEER => false` en todos los proveedores cloud
- **Impacto:** API keys transmitidas sin verificacion SSL (MITM)
- **Fix:** Eliminar la linea, SSL verificacion esta habilitada por defecto

#### SEC-03: Upload de archivos PHP sin bloqueo
- **Archivo:** `src/Storage/FileManager.php:58`
- **Problema:** No hay blocklist de extensiones. Archivos `.php` se suben a `public/uploads/`
- **Impacto:** Remote Code Execution via upload de webshell
- **Fix:** Bloquear extensiones ejecutables (.php, .phtml, .phar, .php5)

#### SEC-04: Local File Inclusion via templates
- **Archivo:** `src/Theme/ThemeManager.php:103`
- **Problema:** `$template` no se sanitiza contra `../`. Usa `require` para incluir archivos
- **Impacto:** LFI -> lectura de archivos sensibles (.env, config) + potencial RCE
- **Fix:** Validar que el path resuelto este dentro del directorio de temas

#### SEC-05: Path Traversal en FileManager
- **Archivos:** `FileManager.php` metodos `delete()`, `exists()`, `move()`
- **Problema:** `$path` no se valida contra secuencias `../`
- **Impacto:** Eliminacion/lectura de archivos fuera del directorio de uploads
- **Fix:** `realpath()` + validar que el path resuelto comience con `$uploadPath`

#### SEC-06: CSRF Middleware no funcional
- **Archivo:** `src/Core/Middleware/CSRFMiddleware.php:99-113`
- **Problema:** Tokens almacenados en `static $storedToken` (memoria del proceso)
- **Impacto:** Token desaparece entre requests. CSRF nunca se valida correctamente
- **Fix:** Almacenar tokens en sesion PHP (`$_SESSION`)

---

### 1.2 ALTAS

#### SEC-07: SQL Injection via ORDER BY
- **Ruta:** `ContentController:47` -> `ContentRepository:106` -> `QueryBuilder:410`
- **Problema:** `$request->query('order_by')` fluye sin validar hasta una clausula SQL
- **Fix:** Whitelist de columnas permitidas en el controller

#### SEC-08: SQL Injection en QueryBuilder (columnas/operadores)
- **Archivo:** `src/Database/QueryBuilder.php:447`
- **Problema:** `$column` y `$operator` se interpolan directamente en SQL
- **Fix:** Validar operadores contra whitelist, escapar/quotear nombres de columna

#### SEC-09: SQL Injection en EntityMaterializer
- **Archivo:** `src/Agent/Data/EntityMaterializer.php:148`
- **Problema:** Nombres de columna de filtros de usuario interpolados en WHERE
- **Fix:** Validar contra schema definido

#### SEC-10: Deserializacion insegura en FileAdapter
- **Archivo:** `src/Cache/FileAdapter.php:45`
- **Problema:** `unserialize($content)` sin `allowed_classes: false`
- **Impacto:** PHP Object Injection si se compromete el directorio de cache
- **Fix:** Usar `json_encode/json_decode` o `unserialize($content, ['allowed_classes' => false])`

#### SEC-11: Credenciales de API en texto plano
- **Archivo:** `src/Agent/Integration/IntegrationManager.php:313`
- **Problema:** API keys almacenadas como archivos `.key` sin cifrar
- **Fix:** Cifrar con `openssl_encrypt()` + clave derivada del JWT_SECRET

#### SEC-12: Bypass de Rate Limiting via X-Forwarded-For
- **Archivo:** `src/Core/Middleware/RateLimitMiddleware.php:69`
- **Problema:** IP del cliente tomada de header spoofeable
- **Fix:** Usar `$_SERVER['REMOTE_ADDR']` como fuente autoritativa

#### SEC-13: .env injection en InstallController
- **Archivo:** `src/Install/InstallController.php:234`
- **Problema:** Valores de usuario interpolados en .env sin escapar newlines
- **Fix:** Sanitizar valores para eliminar `\n`, `\r`

---

### 1.3 MEDIAS

| ID     | Archivo                          | Problema                                              |
|--------|----------------------------------|-------------------------------------------------------|
| SEC-14 | `Connection.php:128`             | SET NAMES con charset interpolado                     |
| SEC-15 | `BackupCommand.php:172`          | `addslashes()` para escape SQL (insuficiente)         |
| SEC-16 | `AuthMiddleware.php:107`         | Acepta tokens sin prefijo Bearer                      |
| SEC-17 | `SecurityHeadersMiddleware:61`   | CSP permite `'unsafe-inline'` para scripts            |
| SEC-18 | `User.php:40`                    | `getAuthorId()` no existe en Content -> escalacion    |

---

## 2. BUGS FUNCIONALES

### 2.1 CRITICOS (Crash en runtime)

| ID     | Archivo                          | Bug                                                    |
|--------|----------------------------------|--------------------------------------------------------|
| BUG-01 | `ConsolidationPipeline.php:7`    | Referencia `AIProviderInterface` eliminada -> fatal    |
| BUG-02 | `MiningPipeline.php:7`           | Referencia `AIProviderInterface` eliminada -> fatal    |
| BUG-03 | `IntegrationManager.php:185`     | Referencia clase `Tool` eliminada -> fatal             |
| BUG-04 | `WorkflowEngine.php:7`           | Referencia clase `Tool` eliminada -> fatal             |
| BUG-05 | `CliGateway.php:7,12`            | Referencia `Chimera` y `GatewayInterface` inexistentes |
| BUG-06 | `AgentFacade.php:97`             | `saveMessages()` llamado sin `$sessionId` requerido    |
| BUG-07 | `Route.php:133`                  | Spreading de array mixto indexed+assoc -> fatal PHP 8  |
| BUG-08 | `ExceptionHandler.php:203`       | `handle()` retorna Response pero nunca llama `send()`  |

### 2.2 ALTOS (Comportamiento incorrecto)

| ID     | Archivo                          | Bug                                                    |
|--------|----------------------------------|--------------------------------------------------------|
| BUG-09 | `AuthMiddleware.php:123`         | Guarda array, PermissionMiddleware espera User object   |
| BUG-10 | `ContentRepository.php:186`      | `update()` ignora `locale` y `translation_group`       |
| BUG-11 | `ContentService.php:269`         | `restoreVersion()` no restaura slug ni type            |
| BUG-12 | `ScaffoldingEngine.php:362`      | Llama `ProviderInterface::chat()` con firma incorrecta |
| BUG-13 | `SearchService.php:187`          | Filtro hibrido pasa items sin el campo de metadata     |
| BUG-14 | `RestoreCommand.php:173`         | `explode(';')` rompe datos con punto y coma            |

### 2.3 MEDIOS

| ID     | Archivo                          | Bug                                                    |
|--------|----------------------------------|--------------------------------------------------------|
| BUG-15 | `CacheManager.php:127`           | `remember()` no cachea valores `null` legitimos        |
| BUG-16 | `CacheManager.php:143`           | Tag-based invalidation es un no-op                     |
| BUG-17 | `MediaController.php:28`         | QueryBuilder compartido acumula estado entre queries   |
| BUG-18 | `ValidationMiddleware.php:33`    | Datos sanitizados nunca se inyectan en el Request      |
| BUG-19 | `Request.php:264`                | `rawBody()` retorna datos incorrectos (stream agotado) |

---

## 3. PROBLEMAS DE RENDIMIENTO

### 3.1 ALTOS

| ID     | Archivo                          | Problema                                               |
|--------|----------------------------------|--------------------------------------------------------|
| PRF-01 | `Router.php:182`                 | Route matching O(n) con regex recompilada por request  |
| PRF-02 | `ContentService.php:398`         | `ensureUniqueSlug()` loop infinito con 1 query/iteracion|
| PRF-03 | `CustomFieldRepository.php:175`  | 2N queries sin transaccion para N custom fields        |

### 3.2 MEDIOS

| ID     | Archivo                          | Problema                                               |
|--------|----------------------------------|--------------------------------------------------------|
| PRF-04 | `Container.php:116`              | Reflection en cada resolucion (no cacheada)            |
| PRF-05 | `HookSystem.php:56,100`          | `ksort()` en cada invocacion de hook                   |
| PRF-06 | `ContentService.php:110`         | Create content no es transaccional (3 ops separadas)   |
| PRF-07 | `ContentService.php:200`         | Delete content no es transaccional (3 ops separadas)   |
| PRF-08 | `ImageProcessor.php:128`         | Re-lee imagen de disco por cada thumbnail              |

---

## 4. CALIDAD DE CODIGO

### 4.1 Patrones Positivos

- **Separacion de capas**: Controller -> Service -> Repository bien definida en Content
- **Adapter Pattern**: Cache (5 adapters), Search (4 embedding providers)
- **Value Objects inmutables**: `StoredFile`, `LLMResponse`, `ToolCall`, `Message` con `readonly`
- **Hook System extensible**: Acciones y filtros WordPress-style para plugins
- **Type Safety**: `declare(strict_types=1)` en la mayoria de archivos
- **PHP 8.1+ moderno**: Constructor promotion, enums, `match`, named args, union types

### 4.2 Anti-patrones Detectados

| ID     | Archivo                          | Anti-patron                                            |
|--------|----------------------------------|--------------------------------------------------------|
| ARC-01 | `AgentServiceProvider.php`       | God class: 610 lineas, 39 funciones, mezcla DI + rutas|
| ARC-02 | `Application.php:133`            | Hard-codea registro de servicios (deberia usar providers)|
| ARC-03 | 4 LLM providers                  | Curl boilerplate duplicado (15+ lineas cada uno)       |
| ARC-04 | 3 pipelines                      | `extractJson()` duplicado en 3 archivos                |
| ARC-05 | Backup/Restore                   | `copyDirectory()`/`removeDirectory()` duplicados       |
| ARC-06 | `WorkflowEngine.php`             | Tool registry propio, incompatible con ToolRegistry    |
| ARC-07 | Bridges                          | Sin BridgeInterface formal (solo convencion implicita) |
| ARC-08 | `MediaController.php`            | Viola SRP: HTTP + queries directas (no service layer)  |

### 4.3 Codigo Muerto

| Archivo                          | Codigo muerto                                          |
|----------------------------------|--------------------------------------------------------|
| `ValidationMiddleware.php:68`    | `sanitizeRequest()` nunca invocado                     |
| `RateLimiter.php:112`            | `clear()` es un no-op                                  |
| `Response.php:32`                | `$statusTexts` + `getStatusText()` nunca usados        |
| `OpenAPIGenerator.php:100`       | `convertPathToOpenAPI()` funcion identidad              |
| `JWTManager.php:83`              | `isExpired()` redundante (JWT::decode ya valida exp)   |

---

## 5. MODULO AGENTICO - ANALISIS ESPECIAL

### 5.1 Estado de Migracion (Parcialmente roto)

El sistema agentico fue migrado de `Tool`/`AIProviderInterface` a `ToolDefinition`/`ProviderInterface`, pero la migracion esta **incompleta**:

| Componente              | Estado      | Problema                                       |
|-------------------------|-------------|-------------------------------------------------|
| AgentLoop               | OK          | Usa ToolDefinition + ProviderInterface          |
| ToolRegistry            | OK          | Registra ToolDefinition                         |
| 5 Bridges               | OK          | Retornan ToolDefinition[]                       |
| MCPServer               | OK          | Funcional                                       |
| WorkflowEngine          | ROTO        | Usa clase Tool eliminada                        |
| IntegrationManager      | ROTO        | Usa clase Tool eliminada + metodo inexistente   |
| ConsolidationPipeline   | ROTO        | Usa AIProviderInterface eliminada               |
| MiningPipeline          | ROTO        | Usa AIProviderInterface eliminada               |
| ScaffoldingEngine       | ROTO        | Firma de chat() incorrecta                      |
| CliGateway              | ROTO        | Referencia clase Chimera inexistente            |

**6 de 10 subsistemas agenticos estan rotos en runtime.**

### 5.2 AntiLoop - Evaluacion

- **Mecanismo:** Compara firmas de tool calls (nombres sorted) entre iteraciones
- **Debilidad:** Solo compara nombres, no argumentos. `search("cats")` y `search("dogs")` se detectan como repeticion
- **Hard cap:** 25 iteraciones maximo (seguro)
- **Veredicto:** Funcional pero demasiado agresivo

### 5.3 MCP Server - Compliance

| Aspecto                    | Estado      |
|----------------------------|-------------|
| JSON-RPC 2.0 base          | Parcial     |
| Protocol version 2025-03-26| OK          |
| tools/list format          | OK          |
| tools/call format          | OK          |
| jsonrpc field validation   | Falta       |
| Session management         | Falta       |
| Batch support (array)      | Falta       |
| Notification handling      | Fragil      |

---

## 6. SHARED HOSTING READINESS

### 6.1 Compatibilidad

| Aspecto                    | Estado      | Notas                                          |
|----------------------------|-------------|-------------------------------------------------|
| PHP 8.1+ puro              | OK          | Sin dependencia de Node.js                     |
| MySQL/MariaDB              | OK          | Compatible con hosting estandar                |
| Sin Redis requerido         | OK          | Cache file-based por defecto                   |
| Sin Composer en produccion  | OK          | vendor/ se sube completo                       |
| .htaccess routing          | FALTA       | No hay archivo .htaccess para Apache            |
| Error display en prod      | RIESGO      | APP_DEBUG no controla display_errors de PHP     |

### 6.2 Recomendacion para Deploy

1. Crear `.htaccess` con `mod_rewrite` para enrutar a `public/index.php`
2. Bloquear acceso a `.env`, `config/`, `storage/` via `.htaccess`
3. Mover uploads fuera de `public/` o agregar regla anti-PHP
4. Configurar `APP_DEBUG=false` y `display_errors=Off`

---

## 7. PLAN DE ACCION PRIORIZADO

### Fase 1: Seguridad (Inmediato)
1. [ ] Fix SEC-01: Sandbox para ShellBridge
2. [ ] Fix SEC-02: Habilitar SSL verification
3. [ ] Fix SEC-03: Blocklist de extensiones en upload
4. [ ] Fix SEC-04: Sanitizar paths en ThemeManager
5. [ ] Fix SEC-05: Validar paths en FileManager
6. [ ] Fix SEC-07/08: Whitelist en QueryBuilder

### Fase 2: Bugs criticos (1-2 dias)
7. [ ] Fix BUG-01 a BUG-06: Migrar subsistemas a ProviderInterface/ToolDefinition
8. [ ] Fix BUG-07: Route parameter spreading
9. [ ] Fix BUG-09: AuthMiddleware/PermissionMiddleware type alignment
10. [ ] Fix BUG-10/11: Content update/restore locale+slug

### Fase 3: Estabilidad (1 semana)
11. [ ] Fix PRF-02: Slug uniqueness con single query
12. [ ] Fix PRF-06/07: Transacciones en Content CRUD
13. [ ] Fix BUG-16: Tag-based cache invalidation real
14. [ ] Crear .htaccess para Apache
15. [ ] Agregar tests de integracion HTTP

### Fase 4: Calidad (Continuo)
16. [ ] Refactorizar AgentServiceProvider (dividir en 3+ clases)
17. [ ] Extraer HTTP client compartido para LLM providers
18. [ ] Agregar `declare(strict_types=1)` a todos los archivos
19. [ ] Implementar BridgeInterface
20. [ ] Documentar API endpoints con OpenAPI

---

*Analisis generado por Chimera NoWP Code Analyzer - 2026-03-26*
