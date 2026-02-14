# Plan de Implementación: WordPress Alternative Framework

## Resumen

Este plan implementa un framework/BaaS moderno como alternativa a WordPress, diseñado para funcionar en servidores compartidos económicos ($3 USD/mes) con arquitectura API-first y capacidades modernas de desarrollo para 2026.

## Tareas

- [x] 1. Configurar estructura del proyecto y dependencias base
  - Crear estructura de directorios (src/, public/, config/, migrations/, plugins/, themes/)
  - Configurar Composer con autoloading PSR-4
  - Instalar dependencias: PHP-JWT, Intervention/Image, PHPUnit/Pest, Faker
  - Crear archivo .htaccess para Apache con mod_rewrite
  - Crear archivos de configuración base (app.php, database.php, cache.php)
  - _Requirements: 1.1, 1.5, 10.4, 10.5_

- [x] 2. Implementar Core Application y Dependency Injection
  - [x] 2.1 Crear Container de DI con registro y resolución de dependencias
    - Implementar Container.php con métodos bind(), singleton(), resolve()
    - Soportar autowiring de dependencias mediante reflection
    - _Requirements: 1.1_
  
  - [x] 2.2 Crear Application class con bootstrap y lifecycle
    - Implementar Application.php con boot(), handle(), registerServiceProviders()
    - Cargar configuración desde archivos config/
    - Inicializar container y registrar servicios core
    - _Requirements: 1.1, 1.4_
  
  - [x] 2.3 Crear Request y Response classes
    - Implementar Request.php para encapsular datos HTTP
    - Implementar Response.php con soporte JSON
    - _Requirements: 1.1_

- [x] 3. Implementar Router y sistema de rutas
  - [x] 3.1 Crear Router con registro de rutas RESTful
    - Implementar Router.php con métodos get(), post(), put(), delete()
    - Soportar parámetros de ruta dinámicos (/api/contents/{id})
    - Implementar route groups con prefijos y middleware compartido
    - _Requirements: 1.1_
  
  - [ ]* 3.2 Escribir property test para Router
    - **Property: Para cualquier ruta registrada, el router debe encontrar la ruta correcta cuando se hace match**
    - **Validates: Requirements 1.1**
  
  - [x] 3.3 Implementar sistema de middleware
    - Crear interfaz MiddlewareInterface
    - Implementar pipeline de middleware
    - _Requirements: 1.1_

- [x] 4. Implementar capa de base de datos
  - [x] 4.1 Crear Connection class con manejo de conexiones MySQL
    - Implementar Connection.php usando PDO
    - Configurar prepared statements por defecto
    - Implementar reintentos de conexión (hasta 3 veces)
    - _Requirements: 1.2, 5.1, 5.5_
  
  - [ ]* 4.2 Escribir property test para reintentos de conexión
    - **Property 13: Reintentos de conexión**
    - **Validates: Requirements 5.5**
  
  - [x] 4.3 Crear QueryBuilder fluido
    - Implementar QueryBuilder.php con métodos table(), select(), where(), join(), etc.
    - Usar prepared statements para todos los valores
    - Soportar operaciones CRUD
    - _Requirements: 5.1, 5.2, 5.4_
  
  - [ ]* 4.4 Escribir property test para prevención de SQL injection
    - **Property 12: Prevención de SQL injection**
    - **Validates: Requirements 5.2**
  
  - [x] 4.5 Implementar sistema de transacciones
    - Añadir métodos beginTransaction(), commit(), rollback() a Connection
    - Implementar rollback automático en caso de excepción
    - _Requirements: 5.6_
  
  - [ ]* 4.6 Escribir property test para rollback automático
    - **Property 14: Rollback automático en transacciones**
    - **Validates: Requirements 5.6**
  
  - [x] 4.7 Crear sistema de migraciones
    - Implementar Migration base class con up() y down()
    - Crear MigrationRunner para ejecutar migraciones
    - Crear tabla migrations para tracking
    - _Requirements: 5.3_

- [x] 5. Checkpoint - Verificar capa de datos
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

- [x] 6. Implementar sistema de autenticación JWT
  - [x] 6.1 Crear PasswordHasher con bcrypt
    - Implementar PasswordHasher.php con hash() y verify()
    - Usar bcrypt con factor de trabajo 10
    - _Requirements: 3.6_
  
  - [x] 6.2 Implementar JWTManager para generación y validación de tokens
    - Crear JWTManager.php con generateToken(), parseToken(), isExpired()
    - Usar librería PHP-JWT
    - Incluir claims: sub, email, role, iat, exp
    - _Requirements: 3.1, 3.2_
  
  - [ ]* 6.3 Escribir property test para tokens con expiración
    - **Property 6: Tokens con expiración configurable**
    - **Validates: Requirements 3.2**
  
  - [ ]* 6.4 Escribir property test para rechazo de tokens expirados
    - **Property 7: Rechazo de tokens expirados**
    - **Validates: Requirements 3.3**
  
  - [x] 6.5 Crear AuthMiddleware para validar tokens en requests
    - Implementar AuthMiddleware.php que valida JWT en header Authorization
    - Retornar 401 si token inválido o expirado
    - Inyectar User en Request si token válido
    - _Requirements: 3.3_
  
  - [x] 6.6 Implementar sistema de roles y permisos
    - Crear enum UserRole (admin, editor, author, subscriber)
    - Implementar métodos hasPermission() y can() en User model
    - Crear PermissionMiddleware para validar permisos
    - _Requirements: 3.4, 3.5_
  
  - [ ]* 6.7 Escribir property test para control de acceso
    - **Property 8: Control de acceso basado en permisos**
    - **Validates: Requirements 3.5**

- [x] 7. Crear modelos de datos y repositorios
  - [x] 7.1 Crear User model con validación
    - Implementar User.php con propiedades readonly
    - Métodos hasPermission(), can()
    - _Requirements: 3.4_
  
  - [x] 7.2 Crear Content model con enums
    - Implementar Content.php con ContentType y ContentStatus enums
    - Métodos toArray(), toJson()
    - _Requirements: 2.1, 2.2_
  
  - [x] 7.3 Crear Media model
    - Implementar Media.php con métodos url(), thumbnailUrl()
    - _Requirements: 6.5_
  
  - [x] 7.4 Implementar ContentRepository
    - Crear ContentRepository.php con métodos CRUD
    - Usar QueryBuilder para consultas
    - Soportar filtros y paginación
    - _Requirements: 2.3_
  
  - [x] 7.5 Crear migraciones de base de datos
    - Migración para tabla users
    - Migración para tabla contents
    - Migración para tabla media
    - Migración para tabla custom_fields
    - _Requirements: 2.1, 2.5_

- [x] 8. Implementar Content Management Service y API
  - [x] 8.1 Crear ContentService con lógica de negocio
    - Implementar ContentService.php con métodos getContent(), createContent(), updateContent(), deleteContent()
    - Integrar con HookSystem para extensibilidad
    - Implementar versionado de contenido
    - _Requirements: 2.1, 2.3, 2.4_
  
  - [ ]* 8.2 Escribir property test para creación de contenido
    - **Property 1: Creación de contenido retorna ID único**
    - **Validates: Requirements 2.1**
  
  - [ ]* 8.3 Escribir property test para respuestas JSON
    - **Property 2: Respuestas de contenido en formato JSON válido**
    - **Validates: Requirements 2.2**
  
  - [ ]* 8.4 Escribir property test para CRUD completo
    - **Property 3: CRUD completo para todos los tipos de contenido**
    - **Validates: Requirements 2.3**
  
  - [ ]* 8.5 Escribir property test para historial de versiones
    - **Property 4: Historial de versiones en actualizaciones**
    - **Validates: Requirements 2.4**
  
  - [x] 8.6 Implementar sistema de custom fields
    - Crear CustomFieldRepository
    - Validación de tipos (string, number, boolean, date)
    - Integrar con ContentService
    - _Requirements: 2.5_
  
  - [ ]* 8.7 Escribir property test para validación de custom fields
    - **Property 5: Validación de tipos en custom fields**
    - **Validates: Requirements 2.5**
  
  - [x] 8.8 Crear ContentController con endpoints REST
    - Implementar ContentController.php con métodos index(), show(), store(), update(), destroy()
    - Registrar rutas en Router
    - Aplicar AuthMiddleware y PermissionMiddleware
    - _Requirements: 1.1, 2.1, 2.2, 2.3_

- [x] 9. Checkpoint - Verificar Content API
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

- [x] 10. Implementar sistema de plugins y hooks
  - [x] 10.1 Crear HookSystem para actions y filters
    - Implementar HookSystem.php con addAction(), doAction(), addFilter(), applyFilters()
    - Soportar prioridades en hooks
    - _Requirements: 4.3_
  
  - [x] 10.2 Crear PluginInterface y PluginManager
    - Definir PluginInterface con métodos register(), boot(), deactivate()
    - Implementar PluginManager.php para cargar y gestionar plugins
    - Validar dependencias de plugins
    - _Requirements: 4.1, 4.2, 4.5_
  
  - [ ]* 10.3 Escribir property test para ejecución de hooks
    - **Property 9: Ejecución de hooks de inicialización**
    - **Validates: Requirements 4.2**
  
  - [ ]* 10.4 Escribir property test para aislamiento de errores
    - **Property 10: Aislamiento de errores de plugins**
    - **Validates: Requirements 4.4**
  
  - [ ]* 10.5 Escribir property test para validación de dependencias
    - **Property 11: Validación de dependencias de plugins**
    - **Validates: Requirements 4.5**
  
  - [x] 10.6 Implementar registro de endpoints personalizados para plugins
    - Permitir que plugins registren rutas mediante API
    - _Requirements: 4.6_
  
  - [x] 10.7 Crear plugin de ejemplo
    - Crear plugin simple que demuestre uso de hooks y registro de endpoints
    - _Requirements: 4.1, 4.2_

- [x] 11. Implementar File Storage y Media Management
  - [x] 11.1 Crear FileManager para subida y gestión de archivos
    - Implementar FileManager.php con upload(), delete(), exists(), url(), move()
    - Validar tipo MIME y tamaño máximo
    - Generar nombres únicos con hash
    - Organizar en estructura año/mes
    - _Requirements: 6.1, 6.2, 6.4, 6.5_
  
  - [ ]* 11.2 Escribir property test para validación de archivos
    - **Property 15: Validación de archivos subidos**
    - **Validates: Requirements 6.1**
  
  - [ ]* 11.3 Escribir property test para nombres únicos
    - **Property 16: Nombres únicos de archivos**
    - **Validates: Requirements 6.2**
  
  - [ ]* 11.4 Escribir property test para organización por fecha
    - **Property 18: Organización de archivos por fecha**
    - **Validates: Requirements 6.4**
  
  - [ ]* 11.5 Escribir property test para URLs públicas
    - **Property 19: URLs públicas válidas**
    - **Validates: Requirements 6.5**
  
  - [x] 11.6 Crear ImageProcessor para procesamiento de imágenes
    - Implementar ImageProcessor.php con resize(), crop(), generateThumbnails()
    - Usar Intervention/Image
    - Generar thumbnails en tamaños configurables
    - _Requirements: 6.3_
  
  - [ ]* 11.7 Escribir property test para generación de thumbnails
    - **Property 17: Generación de thumbnails**
    - **Validates: Requirements 6.3**
  
  - [x] 11.8 Implementar limpieza de archivos huérfanos
    - Crear comando o servicio para detectar y eliminar archivos sin referencias
    - Integrar con eliminación de contenido
    - _Requirements: 6.6_
  
  - [ ]* 11.9 Escribir property test para limpieza de huérfanos
    - **Property 20: Limpieza de archivos huérfanos**
    - **Validates: Requirements 6.6**
  
  - [x] 11.10 Crear MediaController con endpoints REST
    - Implementar MediaController.php con upload(), list(), delete()
    - Registrar rutas en Router
    - _Requirements: 6.1, 6.5_

- [x] 12. Checkpoint - Verificar File Storage
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

- [x] 13. Implementar sistema de caché
  - [x] 13.1 Crear CacheAdapterInterface y adaptadores
    - Definir CacheAdapterInterface con get(), set(), delete(), flush()
    - Implementar APCuAdapter, RedisAdapter, MemcachedAdapter, FileAdapter
    - _Requirements: 11.5, 11.6_
  
  - [x] 13.2 Crear CacheManager con auto-detección
    - Implementar CacheManager.php con remember(), tags(), invalidate()
    - Detectar automáticamente sistema de caché disponible
    - _Requirements: 11.1, 11.5, 11.6_
  
  - [ ]* 13.3 Escribir property test para detección de caché
    - **Property 28: Detección automática de sistema de caché**
    - **Validates: Requirements 11.5**
  
  - [x] 13.4 Integrar caché en ContentService
    - Cachear respuestas de getContent()
    - Invalidar caché en createContent(), updateContent(), deleteContent()
    - _Requirements: 11.1, 11.2_
  
  - [ ]* 13.5 Escribir property test para invalidación de caché
    - **Property 27: Invalidación automática de caché**
    - **Validates: Requirements 11.2**

- [x] 14. Implementar Theme System
  - [x] 14.1 Crear ThemeManager para carga de themes
    - Implementar ThemeManager.php con loadTheme(), renderTemplate()
    - Cargar templates desde directorio themes/
    - Soportar herencia parent/child
    - _Requirements: 8.1, 8.2, 8.3_
  
  - [ ]* 14.2 Escribir property test para uso de theme activo
    - **Property 21: Uso de theme activo**
    - **Validates: Requirements 8.2**
  
  - [ ]* 14.3 Escribir property test para herencia de templates
    - **Property 22: Herencia de templates**
    - **Validates: Requirements 8.3**
  
  - [ ]* 14.4 Escribir property test para fallback de templates
    - **Property 23: Fallback a template por defecto**
    - **Validates: Requirements 8.6**
  
  - [x] 14.5 Crear funciones helper para themes
    - Implementar helpers para acceder a contenido, URLs, configuración
    - _Requirements: 8.4_
  
  - [x] 14.6 Implementar sistema de assets versionados
    - Generar URLs de assets con hash de versión
    - _Requirements: 8.5_
  
  - [x] 14.7 Crear theme por defecto
    - Crear theme básico con templates esenciales
    - _Requirements: 8.1, 8.6_

- [x] 15. Implementar manejo global de errores
  - [x] 15.1 Crear jerarquía de excepciones HTTP
    - Implementar ValidationException, AuthenticationException, AuthorizationException, NotFoundException, RateLimitException, ServerException
    - Cada excepción con método toResponse()
    - _Requirements: 12.1_
  
  - [x] 15.2 Crear ExceptionHandler global
    - Implementar ExceptionHandler.php con handle(), logException()
    - Convertir excepciones a respuestas JSON consistentes
    - Logging detallado de errores
    - _Requirements: 12.1_
  
  - [x] 15.3 Integrar ExceptionHandler en Application
    - Capturar todas las excepciones no manejadas
    - Retornar respuestas de error apropiadas
    - _Requirements: 12.1_

- [x] 16. Implementar características de seguridad
  - [x] 16.1 Crear middleware de validación y sanitización
    - Implementar ValidationMiddleware para validar inputs
    - Sanitizar datos potencialmente peligrosos
    - _Requirements: 12.1_
  
  - [ ]* 16.2 Escribir property test para validación de entradas
    - **Property 29: Validación y sanitización de entradas**
    - **Validates: Requirements 12.1**
  
  - [x] 16.3 Implementar headers de seguridad
    - Añadir SecurityHeadersMiddleware
    - Incluir X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, CSP
    - _Requirements: 12.3_
  
  - [ ]* 16.4 Escribir property test para headers de seguridad
    - **Property 30: Headers de seguridad en respuestas**
    - **Validates: Requirements 12.3**
  
  - [x] 16.5 Implementar rate limiting
    - Crear RateLimiter con almacenamiento en caché
    - Aplicar a endpoints de autenticación
    - _Requirements: 12.4_
  
  - [ ]* 16.6 Escribir property test para rate limiting
    - **Property 31: Rate limiting en intentos de login**
    - **Validates: Requirements 12.4**
  
  - [x] 16.7 Implementar logging de eventos de seguridad
    - Crear SecurityLogger
    - Registrar logins, cambios de permisos, activación de plugins
    - _Requirements: 12.5_
  
  - [ ]* 16.8 Escribir property test para logging de seguridad
    - **Property 32: Logging de eventos de seguridad**
    - **Validates: Requirements 12.5**
  
  - [x] 16.9 Implementar protección CSRF
    - Crear CSRFMiddleware con generación y validación de tokens
    - _Requirements: 12.2_

- [x] 17. Checkpoint - Verificar seguridad
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

- [x] 18. Implementar internacionalización (i18n)
  - [x] 18.1 Crear sistema de traducciones
    - Implementar TranslationManager para cargar archivos de idioma
    - Soportar archivos JSON o PHP para traducciones
    - _Requirements: 14.3_
  
  - [x] 18.2 Implementar detección de idioma
    - Detectar idioma desde header Accept-Language
    - Permitir override mediante parámetro de API
    - _Requirements: 14.4, 14.5_
  
  - [ ]* 18.3 Escribir property test para contenido multiidioma
    - **Property 33: Retorno de contenido en idioma solicitado**
    - **Validates: Requirements 14.2, 14.5**
  
  - [ ]* 18.4 Escribir property test para detección de idioma
    - **Property 34: Detección automática de idioma**
    - **Validates: Requirements 14.4**
  
  - [x] 18.5 Integrar i18n en ContentService
    - Soportar contenido en múltiples idiomas
    - Retornar versión en idioma solicitado
    - _Requirements: 14.1, 14.2_

- [x] 19. Crear API Client TypeScript/JavaScript
  - [x] 19.1 Configurar proyecto TypeScript para cliente
    - Crear directorio client/ con tsconfig.json
    - Configurar build para generar JavaScript y tipos
    - _Requirements: 9.4_
  
  - [x] 19.2 Implementar APIClient class base
    - Crear APIClient.ts con configuración y manejo de tokens
    - Implementar interceptors para requests/responses
    - _Requirements: 9.1, 9.5_
  
  - [x] 19.3 Implementar módulo de autenticación
    - Crear auth module con login(), logout(), refresh(), me()
    - Almacenar tokens automáticamente
    - _Requirements: 9.2_
  
  - [ ]* 19.4 Escribir property test para renovación de tokens
    - **Property 24: Renovación automática de tokens**
    - **Validates: Requirements 9.2**
  
  - [x] 19.5 Implementar reintentos con backoff exponencial
    - Añadir lógica de retry en caso de fallos
    - Usar backoff exponencial
    - _Requirements: 9.3_
  
  - [ ]* 19.6 Escribir property test para reintentos
    - **Property 25: Reintentos con backoff exponencial**
    - **Validates: Requirements 9.3**
  
  - [x] 19.7 Implementar módulos de API (content, media, users)
    - Crear módulos tipados para cada recurso
    - _Requirements: 9.1_
  
  - [x] 19.8 Generar build para navegadores y Node.js
    - Configurar bundling para ambos entornos
    - _Requirements: 9.6_

- [x] 20. Implementar Admin Panel (SPA)
  - [x] 20.1 Configurar proyecto frontend (Vanilla JS + Vite)
    - Crear directorio admin/ con configuración
    - Instalar dependencias y configurar build
    - _Requirements: 7.1_
  
  - [x] 20.2 Crear sistema de autenticación en frontend
    - Implementar login page
    - Redirigir a login si no autenticado
    - Usar API client para autenticación
    - _Requirements: 7.2_
  
  - [x] 20.3 Crear dashboard con estadísticas
    - Mostrar estadísticas de contenido y actividad
    - _Requirements: 7.3_
  
  - [x] 20.4 Implementar gestión de contenido
    - Crear vistas para listar, crear, editar, eliminar contenido
    - Filtros por tipo y estado
    - _Requirements: 7.4_
  
  - [x] 20.5 Implementar gestión de usuarios
    - Crear vistas para gestionar usuarios y roles
    - _Requirements: 7.5_
  
  - [x] 20.6 Implementar gestión de plugins
    - Crear documentación de plugins
    - _Requirements: 7.6_
  
  - [x] 20.7 Implementar gestión de media
    - Crear interfaz para subir y gestionar archivos
    - _Requirements: 6.1, 6.5_
  
  - [x] 20.8 Hacer interfaz responsive
    - Asegurar que funciona en móviles y desktop
    - _Requirements: 7.1_

- [x] 21. Implementar sistema de instalación
  - [x] 21.1 Crear instalador web
    - Implementar InstallController con formulario de configuración
    - Validar requisitos del sistema (PHP, MySQL, extensiones)
    - _Requirements: 10.1, 10.6_
  
  - [ ]* 21.2 Escribir property test para detección de requisitos
    - **Property 26: Detección de requisitos faltantes**
    - **Validates: Requirements 10.6**
  
  - [x] 21.3 Implementar proceso de instalación
    - Crear base de datos y tablas
    - Crear usuario admin inicial
    - Generar configuración
    - _Requirements: 10.2, 10.3_
  
  - [x] 21.4 Crear archivo de configuración de ejemplo
    - Crear .env.example con todas las variables necesarias
    - _Requirements: 10.3_

- [x] 22. Implementar sistema de backup y migración
  - [x] 22.1 Crear comando CLI para exportar backup
    - Implementar BackupCommand para exportar DB y archivos
    - Generar archivo comprimido con timestamp
    - _Requirements: 15.1, 15.2_
  
  - [ ]* 22.2 Escribir property test para generación de backups
    - **Property 35: Generación de backups con timestamp**
    - **Validates: Requirements 15.2**
  
  - [x] 22.3 Crear comando CLI para importar backup
    - Implementar RestoreCommand para importar backups
    - Validar integridad antes de importar
    - _Requirements: 15.3, 15.4_
  
  - [ ]* 22.4 Escribir property test para validación de backups
    - **Property 36: Validación de integridad de backups**
    - **Validates: Requirements 15.4**
  
  - [x] 22.5 Implementar migración de URLs
    - Crear MigrateCommand para actualizar URLs en contenido
    - Buscar y reemplazar URLs en DB y archivos
    - _Requirements: 15.5_
  
  - [ ]* 22.6 Escribir property test para migración de URLs
    - **Property 37: Actualización de URLs en migración**
    - **Validates: Requirements 15.5**

- [x] 23. Implementar documentación OpenAPI
  - [x] 23.1 Generar especificación OpenAPI automáticamente
    - Crear OpenAPIGenerator que analiza rutas y controllers
    - Generar archivo openapi.json
    - _Requirements: 13.1, 13.4_
  
  - [x] 23.2 Integrar Swagger UI
    - Añadir endpoint /api/docs con Swagger UI
    - Cargar especificación OpenAPI generada
    - _Requirements: 13.2_
  
  - [x] 23.3 Añadir ejemplos de código a documentación
    - Incluir ejemplos en PHP, JavaScript, cURL
    - _Requirements: 13.3_
  
  - [x] 23.4 Crear guías de inicio rápido
    - Escribir tutoriales para casos de uso comunes
    - _Requirements: 13.5_

- [x] 24. Checkpoint final - Integración completa
  - Asegurar que todos los tests pasan, preguntar al usuario si surgen dudas.

- [x] 25. Optimización y ajustes finales
  - [x] 25.1 Optimizar rendimiento
    - Revisar queries N+1
    - Añadir índices de base de datos necesarios
    - Optimizar carga de clases con lazy loading
    - _Requirements: 1.3, 1.4, 11.4_
  
  - [x] 25.2 Verificar límites de recursos
    - Testear consumo de memoria (< 256MB)
    - Testear tiempo de respuesta (< 100ms)
    - Testear espacio en disco (< 100MB)
    - _Requirements: 1.3, 1.4, 10.7_
  
  - [x] 25.3 Crear documentación de deployment
    - Guía de instalación en shared hosting
    - Requisitos del sistema
    - Troubleshooting común
    - _Requirements: 10.1, 10.4, 10.5_
  
  - [x] 25.4 Crear ejemplos y demos
    - Crear sitio de ejemplo usando el framework
    - Crear plugins de ejemplo
    - Crear theme de ejemplo
    - _Requirements: 4.1, 8.1_

## Notas

- Las tareas marcadas con `*` son opcionales y pueden omitirse para un MVP más rápido
- Cada tarea referencia requisitos específicos para trazabilidad
- Los checkpoints aseguran validación incremental
- Los property tests validan propiedades de corrección universales según el documento de diseño
- Los unit tests validan ejemplos específicos y casos edge
- El proyecto usa PHP 8.1+ con características modernas (enums, readonly properties, attributes)
- El framework está optimizado para funcionar en servidores compartidos económicos ($3 USD/mes)
- Todas las 37 propiedades de corrección del diseño tienen tareas de property test correspondientes
- El framework usa Pest PHP para testing con mínimo 100 iteraciones por property test
