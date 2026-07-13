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
- ✅ **Seguridad ciudadana / denuncias**: NO estaba en el schema demo `hgo_pachuca`, pero **se migró desde el respaldo de Actopan** (`hgo_actopan`, restaurado con `pg_restore` 17). Ver módulo Seguridad abajo.

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

### 1. Usuarios / Roles / Permisos  ✅ base — ⛔ ETL fuera de alcance
Ya existe y funciona: `UserController`, `RoleController`, páginas `config/users` y `config/roles`, permisos spatie (enums `Permiso`/`Rol` con todos los módulos migrados) + `RoleSeeder`.
- [x] Tablas spatie + CRUD usuarios/roles base
- [x] Catálogo de permisos por módulo (catálogos, directorio, gestión, agenda, invitaciones, notas, reportes×3, tableros×3, capacitación) en `App\Enums\Permiso` + rol Administrador
- [x] Usuario super admin sembrado (bypass vía flag `es_super_admin`)
- ⛔ **ETL de `tbl_usuario` + mapeo `mod_*`: fuera de alcance** (decisión del cliente). Los usuarios se crearán desde la UI; no se migran los 83 legacy ni sus contraseñas.

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
- [x] **Panel de comentarios** en la UI: `ComentarioController` (index/store/destroy polimórfico) + `ComentariosDialog` reutilizable, conectado en beneficiarios/organizaciones/proveedores (menú → Comentarios).
- [x] **Fotos**: se muestran (son data URIs `data:image/...`) y se pueden **subir/reemplazar/quitar** en la ficha (`FotoField` + `FotoController` que sirve la foto bajo demanda para no pesar el listado). Se guardan en la columna `foto`.
  - Nota: extraer las fotos a **spatie/medialibrary** (archivos en disco en vez de base64 en BD) queda como mejora de infraestructura opcional; hoy son funcionales como data URI.

**Decisión:** las 3 tablas de comentarios legacy se consolidaron en una **tabla polimórfica** `tbl_comentarios` (`comentable_type/id`).

**Auditoría:** [x] **Proveedor `representante_id`** (legacy `rep_legal` era un id de beneficiario, no texto): columna + backfill (59) + selector de beneficiario en la ficha.

### 3. Gestión / Solicitudes  🟨 (núcleo — CRUD + turnado completos)
Tablas nuevas: `tbl_solicitudes`, `tbl_seguimientos`, pivotes `tbl_solicitud_rubro`/`tbl_solicitud_sector`, catálogos `cat_rubros`/`cat_conceptos`/`cat_acciones`.
- [x] Migration `2026_07_09_120000_create_gestion_tables.php`
- [x] Models (Solicitud, Seguimiento, CatRubro, CatConcepto, CatAccion) + relaciones. Solicitante **polimórfico** (beneficiario/organización); rubros/sectores **M2M**; seguimientos hasMany.
- [x] ETL `GestionSeeder`: solicitudes **47882** (100% geografía remapeada, solicitante 47700 bene + 181 org), seguimientos **58486**, pivotes 21666/21845. 0 huérfanos.
- [x] `SolicitudController` (paginado) + `SeguimientoController` (turnar/responder) + Form Requests
- [x] Página Inertia: lista + alta/edición (multi-select rubros/sectores, geografía) + **detalle con timeline de seguimientos, turnado a dependencia/área y registro de respuesta**
- [x] Menú "Gestión" + permiso `gestion.gestionar`
- [x] 3 catálogos nuevos añadidos a la pantalla de Catálogos (rubros, conceptos, acciones)
- [ ] **Pendiente:** solicitante tipo **organización** en el alta (hoy el picker es solo beneficiario; el dato migrado sí conserva ambos)

**Decisiones:** `status` 0-6 = Capturada/Turnada/No aprobada/Para resolver/Respuesta de área/Atendida/Atención rápida. `rubro_id`/`sector_id` legacy eran **CSV** → pivotes. Se omitió `obra`, `seccion_resp`.

**Auditoría (cierre de brechas de datos):**
- [x] **`control_administrativo`** (legacy `ctrl_admon`): columna + backfill (2315 de control admin) + filtro en reporte de gestión + checkbox en el alta.
- [x] **`tbl_verificaciones`** (legacy `tbl_verificar`, **12,880** filas): migración + modelo `Verificacion` + ETL + **panel de verificaciones** en el detalle de la solicitud (lista + registrar).

### 4. Agenda / Calendario  ✅  (FullCalendar)
Tablas nuevas: `cat_tipos_evento` (con color), `tbl_eventos`.
- [x] Migration `2026_07_09_130000_create_agenda_tables.php`
- [x] Models (Evento, CatTipoEvento) + relación
- [x] ETL `AgendaSeeder`: 18 tipos, **928 eventos** (100% con fecha parseada), **giras excluidas**. Consolida `fechainicio`+`horainicio` (texto) → timestamps `inicio`/`fin`; banderas int → boolean.
- [x] `AgendaController` con **feed JSON para FullCalendar** (rango start/end) + CRUD + Form Request
- [x] Página React con **@fullcalendar/react** (vistas mes/semana/día, locale es): crear al hacer clic en un día, editar al clic en evento, **arrastrar/redimensionar** para reprogramar, eliminar. Colores por tipo.
- [x] Menú "Agenda" + permiso `agenda.gestionar`

**Decisión:** geografía legacy descartada (venía vacía); el lugar queda en `lugar`. Tipos "Gira de Trabajo" y sus eventos excluidos.

### 5. Notas  ✅
Tablas nuevas: `tbl_notas`, `tbl_notitas` (checklist).
- [x] Migration `2026_07_09_150000_create_notas_tables.php`
- [x] Models (Nota, Notita) + relación + vínculo opcional a evento de agenda
- [x] ETL `NotasSeeder`: **54 notas** (1 vinculada a evento), **3 pendientes**. 0 huérfanos.
- [x] `NotaController` + `NotitaController` (agregar/alternar/eliminar pendientes) + Form Request
- [x] Página React: **grid de tarjetas** con checklist interactivo (marcar/agregar/quitar pendientes), fecha y vínculo a evento
- [x] Menú "Notas" + permiso `notas.gestionar`

### 6. Invitaciones  🟨  (CRUD + vínculo a agenda; envío SMTP pendiente)
Tablas nuevas: `tbl_invitaciones`, `tbl_invitacion_correos`.
- [x] Migration `2026_07_09_140000_create_invitaciones_tables.php`
- [x] Models (Invitacion, InvitacionCorreo) + relación con **evento de agenda** (`evento_id`) y tipo
- [x] ETL `InvitacionesSeeder`: **90 invitaciones** (100% con fecha; 35 vinculadas a eventos), **17 correos**. Fecha+hora texto → timestamps.
- [x] `InvitacionController` (CRUD + notificar + log de correos) + `AgendaController@buscar` (para vincular) + Form Requests
- [x] Página React: tabla + alta/edición con **selector de evento** (`AsyncSearchPicker` reutilizable) + detalle con **log de notificaciones** y registro de correo
- [x] Menú "Invitaciones" + permiso `invitaciones.gestionar`
- [ ] **Pendiente:** envío real por SMTP (hoy `notificar` solo registra el correo; falta Mailable + config de correo)

### 7. Capacitación (FAQs / Manuales / Videos)  ✅
- [x] Página Inertia estática con FAQs (acordeón) + secciones de manuales/videos (placeholders)
- [x] Ruta `capacitacion` + permiso `capacitacion.ver` + menú

### 8. Reportes  ✅  (submódulo con filtros, réplica del legacy; sin obra ni seguridad)
Submenú "Reportes" en el sidebar con 3 reportes independientes, cada uno = formulario de filtros + tabla paginada + export Excel que respeta los filtros.
- [x] **Agenda** (`reportes/agenda`): filtros fecha desde/hasta, tipo de evento, confirmado, con intervención, privado
- [x] **Directorio** (`reportes/directorio`): 3 subtablas (ciudadanos/asociaciones/proveedores) + filtros geográficos (estado/municipio/localidad dependientes), género, sector, ocupación, profesión, estado civil
- [x] **Gestión** (`reportes/gestion`): filtros recepción desde/hasta, estatus (multi), concepto, procedencia, localidad, monto mín/máx
- [x] `ReporteController` (index paginado + `*Excel`) + export genérico reutilizable `QueryExport` (respeta filtros) + `ReporteShell` (componente compartido)
- [x] Permisos `reportes.agenda/directorio/gestion` + submenú. Verificado en navegador.
- [ ] Pendiente: reporte **Actividades** (auditoría de uso) — requiere logging de accesos (hoy no migrado); selección dinámica de columnas (checkboxes) como el legacy

### 9. Tableros  ✅  (3 tableros independientes, submenú en sidebar, con Highcharts)
Réplica idéntica en estructura de los tableros legacy (`index.php` + `tablero.php`), sin Obra pública. Gráficas con **Highcharts** (dona/pie, columnas, barras agrupadas, gauge con bandas rojo/amarillo/verde) igual que el original (que usaba Google Charts).
- [x] **Ejecutivo** (`tableros/ejecutivo`): agenda hoy/mañana + Estatus (dona) + Turnadas/Por resolver por dependencia (columnas) + Cumplimiento (gauge)
- [x] **Gestión** (`tableros/gestion`): 3 statsBars (general, asociaciones gestoras, ciudadanos gestores) + Cumplimiento + Por resolver/Atendidas por dependencia (dona) + Atendidas por concepto (columnas) + Por tipo de beneficiario (barras agrupadas) + Top 10 localidad + tabla de desglose
- [x] **Financiero** (`tableros/financiero`): inversión total + inversión por tipo de beneficiario (barras) + Origen de inversión (dona) + Top 10 localidad por inversión (columnas)
- [x] `TableroController` con las agregaciones exactas del legacy (último seguimiento por dependencia vía `DISTINCT ON`, matriz tipo×clase de beneficiario, `SUM(monto)`)
- [x] Submenú **"Tableros"** en el sidebar con los 3 (permisos `tableros.ejecutivo/gestion/financiero`). `dashboard` redirige al ejecutivo.
- [x] Componentes Highcharts reutilizables (`components/charts/*`)
- [ ] Mejora futura: filtro por localidad/tipo (el legacy lo tenía en Gestión); revisión visual en navegador

---

## Riesgos / notas
- 🔐 `cuentas/conexiones/index.php` legacy tiene credenciales de producción en texto plano → **rotar**, nunca portar.
- `tbl_localidad` (255k) es catálogo INEGI: seed una vez, no editable por usuario.
- `tbl_log` (1.1M) no se migra; se sustituye por laravel-auditing.
- Passwords legacy: verificar algoritmo de `tbl_usuario.pass`/`e_pass` antes del ETL; probablemente requiera reset o re-hash bcrypt.

---

## Auditoría legacy vs. migración (revisión con lupa)

Comparación exhaustiva del legacy contra la migración. Clasificado por si hay **datos reales en el schema demo**.

### ✅ Bloque A — Brechas de DATOS reales (CERRADAS)
- `tbl_verificaciones` (12,880) — módulo de verificación de satisfacción de solicitudes. **Migrado + UI.**
- `solicitud.control_administrativo` (2,315) — tipo ciudadana/control administrativo. **Migrado + filtro + alta.**
- `proveedor.representante_id` (59) — rep. legal es un beneficiario. **Migrado + UI.**
- `cat_tipos_evento` editable en Catálogos. **Agregado.**

### 🟡 Bloque B — Flujo/feature (datos presentes, comportamiento incompleto) — PENDIENTE (decisión)
- [x] Flujo de **atención completo**: al **atender** una solicitud se captura el apoyo (cantidad/unidad/monto/num_bene/concepto), rubros/sectores/origen-recurso y geografía/folio de respuesta, con las 3 decisiones legacy: **Respuesta temporal** (avance, estatus 4), **Atención rápida** (estatus 6) y **Atendida/resuelta** (estatus 5). Componente `AtenderSolicitud` en el detalle → `SolicitudController@atender` (`POST gestion/solicitudes/{id}/atender`) + `AtenderSolicitudRequest`. Relaciones `unidadMedida` (col. `tipo`) y `origenRecurso` (col. `origen`) en el modelo. **Agregado.**
- [x] **Solicitante organización** en el alta: selector Beneficiario/Organización en el diálogo; picker con búsqueda remota (`directorio/organizaciones/buscar` → `OrganizacionController@buscar`). `store()` mapea el morph (`solicitante_type`/`solicitante_id`) según el tipo. La edición precarga el tipo desde `solicitante_tipo`. **Agregado.**
- **Oficio PDF** de solicitud (subir/descargar; el nombre `imagen` está migrado, los archivos físicos viven en el servidor legacy).
- [x] **Reasignar dirección/área a un turnado** (`drccn`): en cada seguimiento sin respuesta, botón "Reasignar dirección" → `SelectField` con las direcciones (áreas) de la dependencia turnada → `PUT gestion/seguimientos/{id}/reasignar` (`SeguimientoController@reasignar`). El backend valida que la dirección pertenezca a la dependencia del turnado (igual que el legacy, que solo cambia `id_area`). **Agregado.**
- [x] **"Notas del evento" desde Agenda**: al abrir un evento en el calendario, panel "Notas del evento" con checklist. La nota es única por evento y se **auto-crea** titulada `NOTAS DEL EVENTO: {título}` (replica `calendario_notas.php`). Endpoints bajo el permiso de agenda: `GET/POST agenda/eventos/{evento}/notas` (`AgendaController@notas`/`agregarNota`, `firstOrNew` por `evento_id`) + toggle/eliminar reutilizando `NotitaController`. **Agregado.**
- `asiste` como enum Presidente/Representante (2 filas).

### 🔵 Bloque C — Features SIN datos en el demo (paridad literal; cero pérdida de datos) — PENDIENTE (decisión)
- **Envío SMTP real** (agenda e invitaciones) — el correo estaba **deshabilitado** en el legacy; el log `invitaciones_correo` sí migrado.
- `agenda_correo` + **recordatorios (cron)** — la tabla no existe en el demo.
- **Capacitación real** (manuales iframe / videos YouTube) — tablas `videos`/`presentacion` no existen en el demo.
- Auto-crear evento de agenda al **confirmar una invitación**.

### ⛔ Fuera de alcance (confirmado, sin brecha)
Obra pública, seguridad/denuncias, electoral (secciones/filiación/militancia/etc.), giras, mapa/geolocalización (comentado en legacy).

---

## Módulo Seguridad ciudadana  ✅ (migrado desde respaldo de Actopan)

No existía en el demo `hgo_pachuca`; se restauró el respaldo de **Actopan** (`hgo_actopan`) con `pg_restore` 17 (el dump es formato PG17) y se migró.

- [x] Migration `2026_07_10_110000_create_seguridad_tables.php`: catálogos (`cat_seg_sectores`, `cat_origenes_denuncia`, `cat_tipos_incidencia`, `cat_niveles_violencia`) + `tbl_denuncias` + `tbl_personas_detenidas` + `tbl_detenidos`.
- [x] Models (7): Denuncia, Detenido, PersonaDetenida + 4 catálogos.
- [x] ETL `SeguridadSeeder` desde `hgo_actopan` (config `legacy_schema_seguridad`): **156 denuncias** (100% geografía → Actopan remapeada a catálogos INEGI), **130 detenidos** (todos ligados a denuncia + persona), catálogos (14 sectores, 5 orígenes, 47 tipos de incidencia, 4 niveles). 0 huérfanos.
- [x] `DenunciaController` (flujo recepción→turnar→atención→conclusión) + `DetenidoController` (con búsqueda de denuncias) + Form Requests.
- [x] Páginas React: **Denuncias** (captura + atención + estatus derivado) y **Detenidos** (con vínculo a denuncia). Submenú "Seguridad" + permiso `seguridad.gestionar`.
- [x] 4 catálogos de seguridad editables en la pantalla de Catálogos.

**Nota de fidelidad de datos:** el denunciante y el detenido son **texto libre** en el legacy (no ligan al directorio), por lo que migrar seguridad de Actopan **no mezcla** con el directorio de Pachuca. Es un módulo autocontenido.
