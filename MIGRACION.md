# Plan de migración — Suite FIEL (PHP puro → Laravel 13 + React/Inertia)

> Documento vivo. Marcar cada checkbox conforme avanza el módulo.
> Origen: `C:\Users\chain\Downloads\suite.fielacceso.com` (PHP puro + PostgreSQL, multi-tenant por schema).
> Demo con datos reales: base `suite_fiel`, **schema `hgo_pachuca`** (misma instancia Postgres).

## Decisiones de arquitectura

| Tema | Decisión |
|---|---|
| Tenancy | **Single-tenant** — una sola BD `suite_fiel`, schema `public`. Se ignoran `id_edo_acceso` / `id_mun_acceso`. |
| Base de datos | PostgreSQL (se conserva). Datos legacy disponibles en schema `hgo_pachuca` → ETL vía `INSERT ... SELECT`. |
| Auth | Fortify + Sanctum + 2FA + passkeys (ya en el starter kit). Reemplaza `$_SESSION["fiel2016_*"]`. |
| Permisos | **spatie/laravel-permission** (ya instalado). Se mapean las columnas `mod_*_master` / `mod_*_N` de `tbl_usuario` a permisos nombrados. |
| Auditoría | **owen-it/laravel-auditing** (ya instalado) reemplaza `tbl_log` (1.1M filas legacy, no se migra). |
| Soft delete | Columna legacy `borrado` (int 0/1) → `SoftDeletes` (`deleted_at`); en ETL `deleted_at = now() where borrado=1`. |
| Auditoría de fila | `id_usuario_act` → `created_by` / `updated_by`. |
| PDF | codedge/laravel-fpdf (ya instalado) reemplaza FPDF legacy. |
| Excel | maatwebsite/excel (ya instalado) para reportes/exportes. |
| Frontend | React + Inertia. **Los modales legacy se reemplazan con los componentes `Dialog` de `resources/js/components/ui`.** |
| Por módulo | Migration + Model (+ relaciones) + Controller + **Form Request** + páginas Inertia. |
| **Nomenclatura de tablas** | **`cat_` para todos los catálogos** (ej. `cat_estados`, `cat_sectores`) y **`tbl_` para entidades/transaccionales** (ej. `tbl_beneficiarios`, `tbl_solicitudes`). Se preserva el estilo del legacy. |
| **Nomenclatura de modelos** | Los modelos de catálogo llevan prefijo **`Cat`** (`CatEstado`, `CatMunicipio`, `CatSector`…). Los de entidad van sin prefijo (`Beneficiario`, `Organizacion`, `Proveedor`). |
| **Geografía compuesta** | Al migrar datos operativos, resolver municipio/localidad por **clave natural** (`estado_id`+`clave`), nunca por id legacy. Endpoints reutilizables: `UbicacionController` (`directorio/ubicaciones/municipios|localidades`). |

## Alcance — EXCLUIDO (no se migra)

- ❌ **Obra pública**: tablas `obra`, `obra_*`, `concepto`, `rubro`, `ped_ejes`, `ped_subejes`; `reportes/obra.php`, `tableros/obra.php`, `pdf/` de obra.
- ❌ **Giras**: subtipo de evento en agenda + `fichas/GIRA*.pdf`. Se filtra el subtipo, no se crea módulo.
- ❌ **Electoral**: tabla `secciones` (1782 secciones electorales) y clasificaciones político-electorales en `beneficiario`: `filiacion_id`, `antagonico_id`, `lider_id`, `influencia_id` + catálogos `cat_filiaciones`, `cat_antagonicos`, `cat_lideres`, `cat_influencias`. **(confirmar si se omiten estos campos del beneficiario)**
- ⚠️ **Seguridad ciudadana / denuncias**: NO existe en el schema demo `hgo_pachuca` (sin `tbl_seguridad`/`tbl_detenido`). Fuera de alcance para esta instancia salvo indicación.

## Convenciones de mapeo (aplican a todas las tablas)

| Legacy | Nuevo |
|---|---|
| `id` (serial) | `id` bigIncrements |
| `borrado` int 0/1 | `deleted_at` (SoftDeletes) |
| `id_usuario_act` | `created_by` / `updated_by` (FK users) |
| `id_edo_acceso`, `id_mun_acceso` | se descartan (single-tenant) |
| `fecha_creacion` / `fecha_captura` / `ultima_update` | `created_at` / `updated_at` |
| FKs por convención (`id_municipio`, `sector_id`, …) | FK explícitas + relaciones Eloquent |

---

## Seguimiento por módulo

Leyenda: ⬜ pendiente · 🟨 en curso · ✅ hecho

### 0. Fundación / Catálogos  ✅
16 catálogos fundacionales migrados, con CRUD y verificados (0 huérfanos, conteos exactos, type-check + ETL OK).
- [x] Migration `2026_07_09_100000_create_catalogos_tables.php` (16 tablas `cat_*`)
- [x] Models + relaciones (Estado, Municipio, Localidad, TipoLocalidad, Ocupacion, Profesion, EstadoCivil, Sector, SectorOrganizacion, UnidadMedida, OrigenSolicitud, OrigenRecurso, Dependencia, Area, Eje, Subeje)
- [x] ETL `CatalogosSeeder` desde `hgo_pachuca` → cat_estados 32, cat_municipios 2450, cat_localidades 255909, etc.
- [x] Permiso `catalogos.gestionar` + rol Administrador
- [x] CRUD editables (12): `CatalogoRegistry` + `CatalogoController` + `UpsertCatalogoRequest` + página React `config/catalogos` con **pestañas** y `CrudFormDialog`/`ConfirmDeleteDialog`. Ruta `/config/catalogos` (`can:catalogos.gestionar`).
- [x] Enlace "Catálogos" en el menú lateral.
- [x] Modelos renombrados con prefijo `Cat` (16).

Los catálogos geográficos (estados/municipios/localidades) quedan solo como referencia (sin CRUD).

**Decisiones tomadas en el ETL:**
- `tbl_municipio` / `tbl_localidad` tienen **clave compuesta** (INEGI dentro del padre, no única global) → id surrogate + columna `clave` + clave natural única `(estado_id, clave)` / `(estado_id, municipio_id, clave)`. Al migrar datos operativos, resolver municipio/localidad por clave natural, NO por id legacy.
- Beneficiarios abarcan **22 estados** → se conserva el catálogo INEGI nacional completo.
- Catálogos excluidos aquí: `cat_tipo` (tipos de obra), `cat_sectores` (cargos políticos → posible electoral, diferido), `ped_ejes`/`ped_subejes` (obra), `tipo_persona` (vacío). `rubro`/`concepto` van con Gestión (Módulo 3).

### 1. Usuarios / Roles / Permisos  🟨 (iniciado)
Ya existe: `UserController`, `RoleController`, páginas `config/users` y `config/roles`, migración de permisos spatie.
- [x] Tablas spatie + CRUD usuarios/roles base
- [ ] Mapear `mod_*_master` y `mod_*_N` (dashboard, reportes, agenda, directorio, gestión, notas, invitación, configuración, faqs) → permisos nombrados
- [ ] Seeder de roles/permisos
- [ ] ETL usuarios desde `tbl_usuario` (rehash de password) + `tbl_usuarios_roles`

### 2. Directorio  🟨 (CRUD completo; pendiente panel de comentarios y fotos)
Tablas nuevas: `tbl_beneficiarios`, `tbl_organizaciones`, `tbl_proveedores`, `tbl_comentarios` (polimórfica).
- [x] Migration `2026_07_09_110000_create_directorio_tables.php` (4 tablas)
- [x] Models (Beneficiario, Organizacion, Proveedor, Comentario) + relaciones + comentarios **polimórficos** (morphMany)
- [x] ETL `DirectorioSeeder`: beneficiarios **26953** (100% geografía remapeada), organizaciones 187, proveedores 63, comentarios 165. Integridad verificada (0 huérfanos).
- [x] Controllers + Form Requests (Beneficiario/Organizacion/Proveedor) con paginación server-side
- [x] `UbicacionController` (municipios/localidades dependientes) + búsqueda de beneficiarios
- [x] Páginas Inertia + `CrudFormDialog`/`ConfirmDeleteDialog` + selects geográficos dependientes + selector de representante
- [x] Enlace "Directorio" en menú + permiso `directorio.gestionar`
- [x] Campos electorales omitidos (filiación, antagónico, líder, influencia, sección, militancia)
- [ ] **Pendiente:** panel de comentarios en la UI (datos ya migrados a `tbl_comentarios`)
- [ ] **Pendiente:** fotos — hoy se migran como base64 en columna `text`; mover a spatie/medialibrary

**Decisión:** las 3 tablas de comentarios legacy se consolidaron en una **tabla polimórfica** `tbl_comentarios` (`comentable_type/id`).

### 3. Gestión / Solicitudes  ⬜  (núcleo — mayor volumen)
Tablas: `solicitud` (48k), `seguimiento` (58k), `tbl_accion`, `tbl_comentario_bene`.
- [ ] Migrations + Models (Solicitud, Seguimiento, Accion)
- [ ] Relaciones (beneficiario/organización solicitante, dependencia, área, origen, rubro, concepto)
- [ ] Flujo de turnado/seguimiento/respuesta + estatus
- [ ] Controllers + Form Requests
- [ ] Páginas Inertia + Dialogs
- [ ] ETL

### 4. Agenda / Calendario  ⬜
Tablas: `cal_eventos` (1143), `cal_tipos` (20).
- [ ] Migrations + Models (Evento, TipoEvento)
- [ ] Relaciones (tipo, estado/municipio/localidad, usuario)
- [ ] Controller + Form Request + vista calendario React (excluir subtipo "gira")
- [ ] ETL

### 5. Notas  ⬜
Tablas: `notas` (54), `notitas` (3, checklist).
- [ ] Migrations + Models (Nota, Notita) + relación
- [ ] Controller + Form Request + Dialogs
- [ ] ETL

### 6. Invitaciones  ⬜
Tablas: `invitaciones` (90), `invitaciones_correo` (17).
- [ ] Migrations + Models + relación con evento de agenda
- [ ] Controller + Form Request + envío de correo (Mailable)
- [ ] ETL

### 7. Capacitación (FAQs / Manuales / Videos)  ⬜
Contenido estático (sin tablas propias relevantes).
- [ ] Páginas Inertia estáticas / editable simple

### 8. Reportes  ⬜  (solo lectura; sin obra ni seguridad)
- [ ] Reporte Agenda
- [ ] Reporte Directorio
- [ ] Reporte Gestión
- [ ] Exportes Excel/PDF

### 9. Tableros / Dashboard  ⬜
Agregados sobre gestión / agenda / directorio.
- [ ] Widgets/KPIs + gráficas
- [ ] Filtros por periodo/área/estatus

---

## Riesgos / notas
- 🔐 `cuentas/conexiones/index.php` legacy tiene credenciales de producción en texto plano → **rotar**, nunca portar.
- `tbl_localidad` (255k) es catálogo INEGI: seed una vez, no editable por usuario.
- `tbl_log` (1.1M) no se migra; se sustituye por laravel-auditing.
- Passwords legacy: verificar algoritmo de `tbl_usuario.pass`/`e_pass` antes del ETL; probablemente requiera reset o re-hash bcrypt.
