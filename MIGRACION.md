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

- ✅ **Obra pública**: estaba excluida, **se reincorporó**. Ver módulo 10 abajo.
- ❌ **Giras**: subtipo de evento en agenda + `fichas/GIRA*.pdf`. Se filtra el subtipo, no se crea módulo.
- ✅ **Electoral**: estaba excluido, **se reincorporó** por decisión del cliente (2026-08-07). Ver "Capa electoral" abajo.
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

### 2. Directorio  ✅
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
- [x] **Paridad de UI con el legacy** (2026-08-05): se rehízo para calcar `directorio/index.php`.
  - Las **tres pantallas sueltas pasaron a ser una sola con tres pestañas**: *Individuos* · *Asociaciones / Grupos organizados* · *Proveedores / Contratistas* (`DirectorioController` + `directorio/index`). El menú lateral lleva **una sola entrada "Directorio"** (sin submenú); las rutas viejas (`/directorio/beneficiarios`, etc.) **redirigen** a su pestaña para no romper enlaces.
  - Buscador con las casillas **"Buscar en": Nombres · Comentarios · Trabajo** y un botón **Más filtros** (ubicación, profesión, sector, sexo). **No lista nada hasta buscar**, como el legacy.
  - Los resultados son **tarjetas** (foto, nombre, clasificación, ubicación y contacto con enlaces `tel:` / `mailto:`), no tablas. La foto y el nombre abren la **ficha de detalle** — el `info_contacto.php` / `info_grupo.php` / `info_prov.php` del legacy, que antes no existía.
  - `FotoController@imagen` sirve la foto como imagen (`/directorio/{tipo}/{id}/foto.img`) para que las tarjetas la carguen con `<img>` y el navegador la cachee, en vez de mandar los data URI en el payload.
  - Los formularios de alta/edición se conservaron íntegros; sólo se movieron a diálogos propios y precargan por `…/{id}/registro`.
- [x] **Vista tarjetas / tabla** intercambiable, con la preferencia guardada en `localStorage` (`directorio:vista`). Los encabezados de la tabla cambian según la pestaña; los datos son los mismos, sin consulta extra.
- [x] **Ida y vuelta con Gestión**: cada registro tiene la acción **Solicitudes**, que abre `gestion/solicitudes?solicitante_tipo=…&solicitante_id=…` con su expediente (sólo individuos y organizaciones: los proveedores no pueden ser solicitantes). En sentido inverso, **"Editar solicitante"** de Solicitudes abre `directorio?tab=…&editar=<id>&q=<nombre>` con el diálogo de edición ya puesto.
- [x] **Monograma** (`monograma.tsx`): como casi ningún registro tiene foto, la miniatura muestra las iniciales sobre un color derivado del nombre — estable por registro, apagado a propósito. Se reutiliza en tarjeta, tabla y ficha. El listado manda `tiene_foto`, así que ya no se dispara una petición de imagen por tarjeta que iba a responder 404.
- [x] Pruebas `tests/Feature/Directorio/DirectorioTest.php` (7) — búsqueda simultánea en las tres pestañas, casillas "Buscar en", filtro por sexo, registro para editar, apertura por `?editar` y redirección de las rutas viejas.

**Fuera de paridad (a propósito):** los filtros **Sección** y **Militancia** del buscador legacy son campos electorales excluidos del alcance (no se migraron las columnas).
  - Nota: extraer las fotos a **spatie/medialibrary** (archivos en disco en vez de base64 en BD) queda como mejora de infraestructura opcional; hoy son funcionales como data URI.

**Decisión:** las 3 tablas de comentarios legacy se consolidaron en una **tabla polimórfica** `tbl_comentarios` (`comentable_type/id`).

**Auditoría:** [x] **Proveedor `representante_id`** (legacy `rep_legal` era un id de beneficiario, no texto): columna + backfill (59) + selector de beneficiario en la ficha.

### 3. Gestión / Solicitudes  🟨 (núcleo — CRUD + turnado completos)
Tablas nuevas: `tbl_solicitudes`, `tbl_seguimientos`, pivotes `tbl_solicitud_rubro`/`tbl_solicitud_sector`, catálogos `cat_rubros`/`cat_conceptos`/`cat_acciones`.
- [x] Migration `2026_07_09_120000_create_gestion_tables.php`
- [x] Models (Solicitud, Seguimiento, CatRubro, CatConcepto, CatAccion) + relaciones. Solicitante **polimórfico** (beneficiario/organización); rubros/sectores **M2M**; seguimientos hasMany.
- [x] ETL `GestionSeeder`: solicitudes **47882** (100% geografía remapeada, solicitante 47700 bene + 181 org), seguimientos **58486**, pivotes 21666/21845. 0 huérfanos.
- [x] `SolicitudController` + `SeguimientoController` + Form Requests
- [x] Menú "Gestión" + permiso `gestion.gestionar`
- [x] 3 catálogos nuevos añadidos a la pantalla de Catálogos (rubros, conceptos, acciones)
- [x] Solicitante tipo **organización** en el alta (picker de beneficiario u organización)
- [x] **Paridad de UI con el legacy** (2026-08-05): la pantalla se rehízo para calcar `solicitudes/index.php`.
  - Buscador con **dependencia + estatus + 9 casillas de "buscar por"**; no lista nada hasta buscar.
  - Listado **agrupado por solicitante** (fila padre con localidad y teléfonos + botones *Editar solicitante* / *Agregar solicitud*) en dos tablas: Solicitantes y Organizaciones.
  - **Un diálogo por acción**, y los botones dependen del `status`, como el legacy:
    | Botón | Pantalla legacy | Estatus |
    |---|---|---|
    | Editar solicitud | `compromiso_edit.php` | 0 |
    | Eliminar | — | 0, 1, 3 |
    | Seguimiento | `seguimiento_add.php` | 0, 1, 3, 4 |
    | Atender/Resolver | `seg_turnar.php` (1) / `seg_resolver.php` (3, 5) | 1, 3, 5 |
    | Verificar | `vrfccn.php` | 5, 6 |
  - Seguimiento: select **TURNAR / NO APROBADA / PARA RESOLVER** + dependencia → dirección → responsable → instrucción → comentario, con fecha compromiso y localidad de entrega. El estatus elegido se copia tal cual a la solicitud.
  - Atender/Resolver: historial de seguimientos como **tabla de 9 columnas** + radios *Respuesta temporal / Atención rápida / Resolver solicitud*; sólo "Resolver" pide el detalle del apoyo, y **sólo cierra la solicitud (status 5) si la captura quedó completa**, igual que el legacy.
  - Alta/edición: origen de la solicitud, fecha compromiso, localidad de entrega, lat/long, *¿quién es el beneficiario?* (`bene_final`), clasificación e imagen. **El estatus ya no se captura a mano**: lo mueve el flujo.
- [x] Migration `2026_08_05_120000_add_notificacion_to_solicitudes.php` + endpoint `notificar` ("ciudadano notificado", sólo en solicitudes no aprobadas).
- [x] Pruebas `tests/Feature/Gestion/SolicitudesTest.php` (7) — agrupado, filtros, turnado y reglas de cierre.

**Fuera de paridad (a propósito):**
- **Convertir a Obra** e **Información de obra**: obra pública está fuera de alcance.
- **Archivo PDF adjunto** del alta: el legacy lo guardaba en disco sin columna en `solicitud`; requiere columna + storage. La **imagen** sí quedó (columna `imagen`, data URI, como en Directorio).
- **Origen del recurso**: en el legacy es multi-select (CSV); aquí la columna `origen` guarda un solo id, así que el campo es de selección única.

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
- [x] **Paridad de UI con el legacy** (2026-08-05), contra `agenda/index.php` + la configuración de FullCalendar en `assets/js/app.php`:
  - Arranca en **vista semana** desde las **6:00**, con 1 h de duración por omisión y "+N más" en la vista mes.
  - **Panel lateral con los tipos arrastrables** ("Arrastra un tipo al calendario"): soltar uno abre el alta con el tipo y el horario puestos.
  - **Confirmación** antes de guardar un arrastre o un cambio de duración, como el legacy.
  - La etiqueta del evento muestra **`PRIV ·` + hora + título** y **`LUGAR:`** en un segundo renglón.
  - Formulario completo: se agregaron **Recomendaciones/Instrucciones**, **Número de personas** y **Localidad**; `asiste` pasó de sí/no a **Presidente (a) / Representante**, con el campo Representante visible sólo en el segundo caso.
- [x] Migration `2026_08_05_140000_ajustes_agenda_legacy.php`: `localidad_id` + `asiste` de boolean a opción (1/2). `AgendaSeeder` actualizado para migrar el valor legacy tal cual.
- [x] **Corregido: los eventos se dibujaban 6 horas fuera de lugar.** El feed emitía `toIso8601String()` (con offset UTC) y el navegador los recorría a su zona; con el corte de las 6:00 los de la mañana temprana ni aparecían. Ahora viajan como hora de pared (`Y-m-d\TH:i:s`), que es la semántica del legacy y la que ya usaban el formulario y el arrastre.
- [x] **Estilo del calendario**: FullCalendar traía su hoja para fondo claro (encabezados ilegibles en oscuro, rejilla brillante, columna de hoy en mostaza). Sus variables se reasignaron a los tokens del tema en `app.css` bajo `.agenda-calendario`, y se marca la **jornada laboral** para que se vea de un vistazo qué cae dentro y qué fuera del horario.
- [x] Pruebas `tests/Feature/Agenda/AgendaTest.php` (4).

**Decisión:** geografía legacy descartada (venía vacía); el lugar queda en `lugar`. Tipos "Gira de Trabajo" y sus eventos excluidos.

### 5. Notas  ✅
Tablas nuevas: `tbl_notas`, `tbl_notitas` (checklist).
- [x] Migration `2026_07_09_150000_create_notas_tables.php`
- [x] Models (Nota, Notita) + relación + vínculo opcional a evento de agenda
- [x] ETL `NotasSeeder`: **54 notas** (1 vinculada a evento), **3 pendientes**. 0 huérfanos.
- [x] `NotaController` + `NotitaController` (agregar/alternar/eliminar pendientes) + Form Request
- [x] Página React: **grid de tarjetas** con checklist interactivo (marcar/agregar/quitar pendientes), fecha y vínculo a evento
- [x] Menú "Notas" + permiso `notas.gestionar`
- [x] **Revisión contra el legacy** (`notas/index.php`, `notas_lista.php`, `notas_cambiar.php`, `calendario.php`). Tres brechas cerradas:
  - **Rango de fechas**, que era el filtro principal de la pantalla legacy. Se conserva su conducta: **sin rango explícito abre en ayer y hoy**, no en el histórico completo (por eso con los datos de demo —2017 a 2021— la pantalla abre vacía; el mensaje lo dice y trae los campos a la vista para ampliarla).
  - **Editar el texto de un pendiente** (el icono de guardar del legacy, `notas_cambiar.php`): ahora se edita en línea, con Enter para guardar y Esc para cancelar. Ruta `PUT notas/pendientes/{notita}`.
  - **Nota huérfana**: si el evento al que colgaba se borró, el legacy deja de listarla (`evento_id=0 OR evento_id IN (…borrado=0)`). Se replicó. En el demo esto **oculta 1 nota** ("NOTAS DEL EVENTO: CUMPLE NIETA", evento 139 borrado), que antes se veía sin su evento.
  - Además, los datos del evento vinculado (título, fecha, hora y lugar) se muestran en la tarjeta; el legacy los enseñaba en una ventana aparte al picarle a "Vinculado" (`calendario.php`).
- [x] Orden `ORDER BY id ASC` como el legacy (la más vieja arriba), y contador de pendientes abiertos por tarjeta y en el encabezado.
- [x] Pruebas `tests/Feature/Notas/NotasTest.php` (7).

- [x] **Diseño**: la pantalla pasó de una retícula tipo *masonry* a un **libro de registro**, que es lo que los datos son en realidad (51 de 53 notas son asientos de oficios: "OFICIO 03198 DIRIGIDO AL LIC. …"). Cada día abre su banda —fecha a la izquierda, número de notas a la derecha— y los asientos se leen de arriba abajo, con la medida acotada a ~75 caracteres. En *masonry* el orden cronológico se rompía —se leía hacia abajo por columna— y las 53 tarjetas se veían todas iguales. Las acciones de cada asiento —y las de cada pendiente— viven en un **menú `…`** (`DropdownMenu` de shadcn), el mismo patrón que Invitaciones y Obra, en lugar de una hilera de iconos: "Editar nota", "Agregar pendiente" y "Eliminar nota". El campo de pendiente sólo se dibuja cuando la nota ya trae alguno o cuando se pide desde el menú, así que dejó de repetirse 52 veces en notas que no llevan ninguno.

**Diferencia de forma conservada a propósito:** el legacy es una **tabla** (Nota · Fecha · Asignado a evento · Acciones) y el checklist vive en una ventana aparte que se abre con "Editar nota". Aquí es una sola pantalla de tarjetas con el checklist a la vista, como se acordó para Directorio. El contenido y las acciones son los mismos.

### 6. Invitaciones  🟨  (revisado contra el legacy; envío SMTP pendiente)
Tablas nuevas: `tbl_invitaciones`, `tbl_invitacion_correos`.
- [x] Migration `2026_07_09_140000_create_invitaciones_tables.php`
- [x] Models (Invitacion, InvitacionCorreo) + relación con **evento de agenda** (`evento_id`) y tipo
- [x] ETL `InvitacionesSeeder`: **90 invitaciones** (100% con fecha; 35 vinculadas a eventos), **17 correos**. Fecha+hora texto → timestamps.
- [x] `InvitacionController` (CRUD + notificar + log de correos) + `AgendaController@buscar` (para vincular) + Form Requests
- [x] Página React: tabla + alta/edición con **selector de evento** (`AsyncSearchPicker` reutilizable) + detalle con **log de notificaciones** y registro de correo
- [x] Menú "Invitaciones" + permiso `invitaciones.gestionar`
- [x] **Revisión contra el legacy** (`invitaciones/index.php`, `nvtcn_add.php`). Dos hallazgos:
  - **La fecha de recepción estaba mal mapeada.** La tabla legacy tiene *dos* columnas parecidas: `fecha_recepcion` (timestamp, cuándo se capturó el registro — un `created_at` disfrazado) y `fecharecp` (varchar, **la fecha que teclea el usuario** en el formulario). El ETL tomó la primera por su nombre, así que se perdieron las **32 fechas capturadas** y los `created_at` quedaron con la fecha del ETL. Corregido en `2026_08_06_140000`: ahora `fecha_recepcion` trae lo que capturó el usuario y `created_at` el timestamp real.
  - Al listado le faltaban dos columnas del legacy: **Remitente** (es el contacto) y **Agenda** (si está vinculada a un evento). Agregadas. Se conservan Tipo, Estado y Correos, que son nuestras.
- [ ] **Pendiente:** envío real por SMTP (hoy `notificar` solo registra el correo; falta Mailable + config de correo)

### 7. Capacitación (FAQs / Manuales / Videos)  ✅ (contenido no migrable)
- [x] Página Inertia estática con FAQs (acordeón) + secciones de manuales/videos (placeholders)
- [x] Ruta `capacitacion` + permiso `capacitacion.ver` + menú

### 8. Reportes  ✅  (submódulo con filtros, réplica del legacy; sin obra ni seguridad)
Submenú "Reportes" en el sidebar con 3 reportes independientes, cada uno = formulario de filtros + tabla paginada + export Excel que respeta los filtros.
- [x] **Agenda** (`reportes/agenda`): filtros fecha desde/hasta, tipo de evento, confirmado, con intervención, privado
- [x] **Directorio** (`reportes/directorio`): 3 subtablas (ciudadanos/asociaciones/proveedores) + filtros geográficos (estado/municipio/localidad dependientes), género, sector, ocupación, profesión, estado civil
- [x] **Gestión** (`reportes/gestion`): filtros recepción desde/hasta, estatus (multi), concepto, procedencia, localidad, monto mín/máx
- [x] `ReporteController` (index paginado + `*Excel`) + export genérico reutilizable `QueryExport` (respeta filtros) + `ReporteShell` (componente compartido)
- [x] Permisos `reportes.agenda/directorio/gestion` + submenú. Verificado en navegador.
- [x] **Reporte de Seguridad** (`reportes/seguridad.php` del legacy): filtros por rango de fechas, etapa, origen, presunto delito, nivel de violencia, sector y localidad, con exporte a Excel.
- [x] **Selección dinámica de columnas en los cuatro reportes** (las casillas de "resultados a mostrar" del legacy). Cada reporte tiene su catálogo en `ReporteController`, tomado uno por uno de los `chk_*` del PHP original, con las mismas etiquetas:
  - **Agenda** 11 columnas (`reportes/agenda.php`) — título, tipo, descripción, contacto/teléfono, fecha, hora, lugar, localidad, ¿quién asiste?, agregado por, clasificación.
  - **Directorio** un catálogo por pestaña: ciudadanos 23, asociaciones 11, proveedores 13. Al cambiar de pestaña se cambia el catálogo y se reinicia lo marcado, porque las columnas de un directorio no significan nada en otro.
  - **Gestión** 22 columnas de la pestaña DETALLE, incluidas las compuestas: dirección del solicitante, rubros, segmentos poblacionales, tipo de beneficiario (`bene_final`: 1 y 2 individual, 3 comunitario), dependencia y área del último seguimiento, verificaciones y tipo de solicitud (`ctrl_admon`).
  - **Seguridad** las 16 que ya tenía.
  - La tabla **y** el Excel salen con lo marcado, siempre **en el orden del catálogo** y no en el que se hizo clic. Lo que no está en el catálogo se descarta (`ColumnasField` + `useColumnas` en el front; `columnasPedidas()` en el back). Pruebas: `tests/Feature/Reportes/ColumnasTest.php` (7).
- [ ] Pendiente: reporte **Actividades** (auditoría de uso) — requiere logging de accesos (hoy no migrado)
- [x] **Las 4 pestañas del reporte de Gestión** (el legacy las tenía como tabs; aquí son botones arriba del formulario, y cambiar de pestaña recarga porque cada vista trae sus propias columnas):
  - **General**: resumen por localidad con 13 columnas elegibles — número de solicitantes (distintos, no solicitudes), monto de inversión, beneficiarios, total y los contadores por estatus, más las convertidas en obra. Se agrega en la base porque son ~47 mil solicitudes; el monto es texto en el legacy, así que se limpia antes de sumar. El Excel exporta lo mismo que se ve (`ColeccionExport`), no la consulta cruda.
  - **Detalle**: la que ya existía, con sus 22 columnas.
  - **Histórico de atendidas / no aprobadas**: las cerradas (estatus 2, 5 y 6) **que no se volvieron obra**, con las 6 columnas fijas del legacy.
  - **Atendidas**: las cerradas sin importar la obra, con 11 columnas fijas, ordenadas por fecha de respuesta. La columna "Fecha registro en plataforma" replica la regla del legacy: la fecha de creación del seguimiento si la solicitud se rechazó, la de respuesta si se atendió.
  - Histórico y Atendidas **no eligen columnas** (el legacy sólo pone casillas en General y Detalle), y el back lo impone aunque la URL pida otra cosa.
- [x] **Rescate de `solicitud.obra`** (`2026_08_06_130000` → `convertida_obra`). Quedó fuera cuando Obra pública estaba excluida, pero el histórico filtra por ella. Valores del legacy: 0 no es obra · 1 **convertida** (`solicitudes/convertir.php`, que además pone la solicitud en estatus 5) · 2 **importada** al módulo de obra. En el demo son **231 convertidas y 121 importadas**, y la diferencia entre Atendidas (45,145) e Histórico (44,793) es exactamente esas 352. El alta y la baja de una obra ahora mueven el flag, como en el legacy.

- [x] **Diseño de las cuatro pantallas de reportes** (sin tocar filtros, columnas ni exportes). La pantalla se lee como lo que es —un formulario que produce un documento— y recupera la separación que el legacy tenía en dos paneles: **"Qué se busca"** y **"Qué columnas se muestran"**, cada uno con su rótulo. Sobre el botón de Excel va, en palabras, lo que va a salir: *"Se descargan 25,421 registros con 9 de 23 columnas"*, que es justo lo que uno quiere confirmar antes de descargar. Corregidos de paso dos defectos reales: las casillas múltiples (presunto delito, sector, estatus) se **desbordaban** —nombres como "ALTERAR EL ORDEN, ARROJAR LÍQUIDOS U OBJETOS…" salían del cuadro con barra horizontal— y ahora reparten columnas según el ancho disponible (`auto-fill`, no un breakpoint); y una celda de texto largo ya no estira la tabla fuera de pantalla. La tabla vacía dice qué hacer en vez de "Sin resultados.".

**Columnas del legacy que no se pudieron traer** (sin dato de origen, no se decidieron a la ligera):
- Directorio → proveedores: **"Contacto directo"** y **"Teléfono del contacto"**. El PHP lee `nom_contacto` / `tel_contacto`, pero esas columnas no existen en `hgo_pachuca.proveedor` (el propio legacy hacía `SELECT *` y las imprimía vacías).
- Gestión: **"¿Es obra pública?"** — sale de `solicitud.obra`, y Obra pública quedó fuera de alcance.
- Agenda → "Agregado por": se muestra quién lo creó (`created_by`). El legacy añadía "Actualizado por: …" leyendo `tbl_log`, que no se migró.

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

### 10. Obra pública  ✅  (datos, 7 etapas, estimaciones, reporte y tablero)
El módulo más grande del legacy: **~20 pantallas** (`obrapublica/`) más `reportes/obra.php` y `tableros/obra.php`.

**Lo que hay en el demo** (todo migrado): **145 obras** (122 vivas), **207 estimaciones**, 142 componentes, 96 beneficios, 96 costos. Las siete etapas están en uso: ficha 91, expediente 90, autorización 90, adjudicación 82, ejecución 62, conclusión 83, entrega 72; 82 con contrato. Las tablas `obra_convenio`, `obra_documentaciones`, `obra_estructura`, `obra_incidencias` y `obra_observaciones*` están **vacías** en el demo.

- [x] **Esquema** (`2026_08_06_100000_create_obra_tables.php`). El legacy guarda todo en **una sola tabla `obra` de 313 columnas**, con las siete etapas pegadas una tras otra. Se partió en: `tbl_obras` (identificación y ficha técnica), una tabla 1:1 por etapa (`autorizacion`, `adjudicacion`, `ejecucion`, `cierre`), las hijas que el legacy ya tenía sueltas (componentes, beneficios, costos, estimaciones) y **`tbl_obra_documentos`**.
- [x] **El hallazgo que ordena el módulo**: de las 313 columnas, **174 son 87 pares `documento` / `obs_documento`** — no son campos, son una **lista de acuse** ("¿está la carta de aceptación? ¿qué se anotó?"). Se colapsaron en `tbl_obra_documentos` (obra, etapa, clave, presente, observación): 174 columnas → 5. Se cargan sólo los renglones con dato: **5,118** en vez de 12,615 vacíos.
- [x] Catálogos nuevos: `cat_pmd_ejes/subejes` (Plan Municipal, 12/39) y `cat_ped_ejes/subejes` (Plan Estatal, 5/28).
- [x] **La contratista es un proveedor del directorio**: `adj_empresa_id` → `tbl_proveedores`, resuelto en las 82 obras con contrato. El legacy además repetía nombre, RFC y dirección como texto y se conservan.
- [x] **Toda obra nace de una solicitud**: `id_solicitud` resuelve 145/145 contra `tbl_solicitudes`. Es el vínculo con Gestión que faltaba (la columna `solicitud.obra` que quedó fuera de alcance).
- [x] ETL `ObraSeeder` + permiso `obra.gestionar`.

**Dos correcciones que salieron al verificar los datos** (no al leer el código):
- `obra.tipo_obra_id` **no es un tipo de obra**: apunta a `tbl_tipo`, que es el catálogo de **unidades de medida** de la meta (KILÓMETRO, M2, PIEZA). Se guarda como `unidad_medida_id` contra el `cat_unidades_medida` que ya existía, en vez de duplicar el catálogo. Resuelve 93/93.
- `obra.colonia_id` se resolvía por id contra `cat_localidades` y sólo casaban 47 de 115: `cat_localidades` usa id propio y guarda el id legacy en `clave`. Corregido el join; resuelve 115/115.

- [x] **Listado y ficha técnica** (paso 1 de 4). `ObraController` + `obra/index.tsx`. El listado replica las columnas del legacy (nombre · localidad · folio de recepción · etapas) con la **cascada de nombre**: el autorizado manda sobre el capturado, y ése sobre el texto de la solicitud. El folio y el texto salen de la relación con la solicitud, no de una copia (se verificó que `nombre_solicitud` coincide 145/145 y `folio_solicitud` = `folio_sistema` 145/145). Las siete etapas se ven como un **riel de colores** con los mismos tonos del legacy; adjudicación y ejecución se marcan bloqueadas mientras la autorización no fije la modalidad de ejecución. Se agregaron filtros por etapa cerrada y por etapa pendiente, y búsqueda por nombre, número, folio o localidad. La ficha técnica se edita en un diálogo con las cuatro secciones del legacy (identificación, ubicación con mapa, alineación a los planes, análisis) y el acuse "dar por cerrada la ficha". Pruebas `tests/Feature/Obra/ObraTest.php` (10).

**Tres bugs de la misma familia, encontrados al probar la edición en el navegador** (los tres estaban en componentes compartidos, así que afectaban a más pantallas):
- `SelectField` y `UbicacionSelects` convertían con `Number(v)` el valor que emite el select al limpiarse. Cuando el valor actual todavía no tiene su opción en la lista (porque llega por `fetch` o depende de otro select), el componente descarta el valor y reporta cadena vacía: **`Number('') = 0`**, así que la edición se abría en blanco y al guardar mandaba `0`, que reventaba la validación. Ahora ambos guardan contra `''` y, sobre todo, **siempre inyectan la opción del valor actual** aunque la lista no haya cargado.
- El diálogo montaba los selects antes de que llegara la ficha; ahora el formulario no se dibuja hasta tener el dato.
- La cascada eje → subeje limpiaba el subeje cada vez que el select del eje re-emitía su propio valor, **borrando el subeje al guardar**. Ahora sólo limpia si el eje de verdad cambió.

- [x] **Las siete pantallas de etapa** (paso 2 de 4). El riel de etapas del listado dejó de ser decorativo: cada tramo abre la lista de papeles de esa etapa, con el acuse de **tres estados** del legacy (sí / no / sin capturar) y su observación, más el "dar por cerrada la etapa". Un solo diálogo sirve a las siete porque el catálogo vive en `App\Support\DocumentosObra`, con **las 84 etiquetas sacadas una por una de las pantallas de `obrapublica/`** (ficha 3, expediente 26, autorización 2, adjudicación 30, ejecución 18, conclusión 1, entrega 4). Al guardar, el papel que queda sin acuse ni observación se borra, para que la tabla sólo tenga renglones con dato. Pruebas `tests/Feature/Obra/EtapasTest.php` (6).
- [x] **Rescate de `invitacion_acto`** (`2026_08_06_110000`). No es un acuse sí/no: guarda **quiénes fueron invitados** al acto de entrega como lista de ids ("1,2,3,4,5" = empresa, contraloría, dependencia normativa, comité de obra, comunidad). El ETL la había leído como sí/no y dejó **57 renglones sin valor**; ahora vive en `tbl_obra_cierre.invitados_acto` y se rehidrató desde el legacy. De paso se retiraron `oficio_invitacion_contrato` y `acta_constitutiva`, que no traen datos y el legacy tampoco dibuja como acuse.

- [x] **Los datos propios de cada etapa** (paso 3 de 4). Arriba de la lista de papeles, cada etapa muestra ahora sus campos: oficios, modalidad de ejecución, metas y estructura financiera en **autorización**; licitación, contratista, contrato y fianzas en **adjudicación**; arranque, responsables, bitácora y pagos en **ejecución**; aviso, finiquito y actas en **conclusión** y **entrega-recepción**. Van agrupados como en las pantallas del legacy, con el catálogo en `App\Support\CamposObra` (`texto`, `fecha`, `area`, `opciones`, `multiple` y `empresa`). Se recuperaron los tres catálogos que el legacy tenía como `<option>` en el HTML: **modalidad de ejecución** (contrato / administración directa / convenio), **tipo de contratación** (5 opciones) y **tipo de bitácora**. La contratista se elige con un buscador contra los proveedores del directorio, y "a quién se invitó" al acto es la lista de casillas del legacy, que se guarda como los ids separados por coma. Conclusión y entrega comparten `tbl_obra_cierre` sin pisarse: cada una sólo escribe sus propios campos. Pruebas ampliadas a 21 en `tests/Feature/Obra/`.

- [x] **Estimaciones, reporte y tablero** (paso 4 de 4).
  - **Estimaciones**: lista por obra con alta, edición y baja. Cada una lleva periodo, monto, calidad, estatus, factura, pago, avance físico y financiero, y la **documentación obligatoria** (9 casillas del legacy) que se guarda como ids separados por coma. Catálogos recuperados del HTML del legacy: tipo (`obra_estimacion.php` graba 1, `obra_est_extra.php` graba 2), estatus (autorizada / no autorizada) y calidad (buena / regular / mala).
  - **Reporte de obra** (`reportes/obra.php`): las **42 columnas** del legacy con su selección dinámica y export a Excel, más filtros por ejercicio fiscal, modalidad de ejecución y tipo de contratación. Varias columnas no son campos: los acuses (dictamen, fallo, acta de fallo, aviso de conclusión, expediente unitario, programa anual) salen de `tbl_obra_documentos` y se leen como Sí / No / vacío; el avance sale de la última estimación que lo traiga.
  - **Tablero de obra** (`tableros/obra.php`, quinto tablero): los siete acuses de etapa del statsBar legacy en el mismo orden, medidor de obras entregadas, en qué punto está el padrón, localidades con mayor inversión y **mapa de las 91 obras georreferenciadas**. Permiso `tableros.obra`.
- [x] **Rescate de `doc_obligatoria`** (`2026_08_06_120000`): igual que `invitacion_acto`, es una lista de ids por coma y trae dato en **las 207** estimaciones; el ETL no la había traído.

- [x] **Diseño de Obra y Notas, armonizado con el resto** (sin tocar datos ni comportamiento). El encabezado compartido salió de `charts/panel.tsx` a **`components/pagina.tsx`** —no era chrome de gráficas— y ahora `Encabezado` acepta `acciones`, así que Obra, Notas y los cinco tableros abren igual: título a la izquierda, la cifra que resume la pantalla y el botón de alta a la derecha. El total de obras y el de notas se subieron del filtro al encabezado; la banda de filtros se quedó sólo con los filtros y con el rango que se está mostrando.
- [x] **Las acciones sueltas pasaron a `DropdownMenu` de shadcn**, el patrón que ya usaba Invitaciones: en el listado de obras ("Ficha técnica", "Estimaciones", "Eliminar"), en cada estimación ("Editar", "Eliminar") y en las notas y sus pendientes. Antes eran iconos que sólo aparecían al pasar el cursor —invisibles hasta que se les atinaba— y sin etiqueta que dijera qué hacían. El riel de etapas no se tocó: sigue siendo el acceso directo a las siete pantallas.
- [x] **La columna de margen** (paso de diseño sobre Notas y el tablero de obra). Las dos pantallas son registros municipales, y en un libro de gobierno el margen izquierdo es una columna reglada donde se anota la manija del asiento —el folio, el número de etapa— para poder hojear en vez de leer. Ese dispositivo es ahora la estructura de ambas; el producto ya lo usaba a medias (`font-mono tabular-nums` para el folio en Solicitudes, y la caja de folio monoespaciada del acta impresa).
- [x] **Notas: el folio sale del texto al margen** (`notas/asiento.ts`). 51 de 53 asientos abren igual —"OFICIO 03016 REFERENTE A…"—, así que el muro de mayúsculas escondía justo el dato por el que se busca. `leerAsiento()` separa **tipo** (OFICIO, LLAMADA, JUICIO DE AMPARO, TARJETA INFORMATIVA…) y **folio** (`STM/SP/0858/2017`, `03016`) y los pone en una banda a la izquierda; el cuerpo arranca ya en la sustancia. Es a propósito estricto: si el texto no abre con un tipo conocido o lo que sigue no tiene forma de folio, se muestra íntegro sin margen —un asiento de los 53 (`JUICIO DE AMPRAO`, con errata) cae por ahí y así debe ser. Comprobado contra las 53 notas: nada de texto se pierde; lo único que se descarta es la palabra "NUMERO" cuando anuncia al folio que ya está en el margen.
- [x] **Tablero de obra: una barra al pie de cada tarjeta.** Las siete tarjetas de acuses del legacy se quedan **tal cual** —mismas cifras grandes, mismo orden—; abajo de cada una corre ahora una línea proporcional al padrón, con el color de etapa del legacy (el mismo que el riel del listado y los alfileres del mapa, exportado desde `obra/etapas.tsx` para que un color siga siendo una sola etapa en todo el producto). Crecen desde cero al cargar, escalonadas 40 ms (`@keyframes crecer-barra`), y con `prefers-reduced-motion` aparecen ya en su ancho. **Lo que la línea deja ver:** 62 obras tienen cerrada la ejecución pero 83 están terminadas —los acuses se cierran por separado y hay 21 expedientes que se saltaron el paso—, algo que con siete tarjetas del mismo tamaño no se notaba.

**Nota de proceso:** en este paso llegué a sustituir esas siete tarjetas por un riel de barras. Se repusieron: un paso de diseño **no quita bloques**, sólo los cambia de aspecto.
- [x] `Encabezado` de `components/pagina.tsx` acepta `acciones`; el pendiente de cada nota y el asiento comparten eje con su menú `…`.
- [x] **Importes y avances con formato** en el diálogo de estimaciones (`lib/money.ts`): `4927057.10` se lee `$4,927,057.10` y `avance 14` se lee `avance 14%`. Lo que no sea número se muestra tal cual, porque varios de estos campos son texto libre en el legacy.

**Un dato sucio que el mapa dejó ver:** una obra guarda `latitud`/`longitud` = `"NaN"`. PHP castea `"NaN"` a `0`, así que el alfiler aparecía en el Golfo de Guinea y el encuadre se llevaba el mapa entero al Atlántico. El tablero ahora exige que el texto parezca número y descarta el (0, 0).


## Capa electoral  ✅  (reincorporada 2026-08-07)

Estaba marcada como excluida con la nota "confirmar si se omiten"; el cliente decidió migrarla. Al revisarla apareció que **la exclusión iba a costar mucho más de lo que decía el pendiente**.

**El hallazgo:** el pendiente hablaba de cuatro campos de clasificación con 25, 3, 3 y 5 registros —ruido— y en el mismo renglón mencionaba las secciones. Pero la **sección electoral no es uno de esos campos**: en el legacy **cuelga de la localidad** (`tbl_localidad.seccion`, poblada en **las 255,909 filas**) y el formulario del beneficiario sólo la **copia** al elegir localidad, con el campo en sólo lectura (`sel_localidad.php`). Por eso **26,771 de 26,953 beneficiarios (99.3%)** la traen, repartidos en 651 secciones. Nuestro `cat_localidades` nunca la trajo: se iba a perder el dato territorial fino con el que trabaja un municipio.

- [x] **`cat_localidades` gana la capa INE** (`2026_08_07_100000`): `seccion`, `distrito_federal` y `tipo_seccion`. Rescatadas **255,909 de 255,909**, resolviendo por clave natural (`clave` + `estado_id`, que casa 1:1 con el `id` legacy — verificado antes de correr el ETL; el join por `clave_ine` explotaba a 106M filas). Tipos: 156,362 RURAL, 56,178 MIXTO(A), 43,369 URBANO(A).
- [x] **4 catálogos** `cat_filiaciones` (11), `cat_antagonicos` (5), `cat_lideres` (9), `cat_influencias` (5), editables desde Configuración → Catálogos.
- [x] **`cat_secciones`** (1,782): el padrón de secciones del estado con su distrito federal y local. El legacy lo consulta en el alta pero **nunca lo llega a mostrar**; se migra porque es el único lugar donde vive el distrito por sección. Municipio resuelto por clave natural, 1,782 de 1,782.
- [x] **Beneficiario**: `seccion` + los 4 `*_id`. Rescatados 26,953 con sección y 25/3/3/5 clasificaciones — los mismos números del legacy.
- [x] **UI**: sección **en sólo lectura** bajo el domicilio ("La define la localidad"), igual que el legacy; sección "Perfil político" con los cuatro selects. `UbicacionSelects` ganó `onSeccion` **opcional** —lo pide sólo el directorio— y `/directorio/ubicaciones/localidades` ahora entrega `seccion` junto al nombre, así que no hace falta la segunda vuelta al servidor que daba el legacy.
- [x] **Ficha** y **reporte de Directorio**: 5 columnas nuevas (Sección electoral, Filiación, Antagónico, Líder, Grado de influencia), con eager load para no hacer N+1.
- [x] Pruebas `tests/Feature/Directorio/PerfilPoliticoTest.php` (4).

**Sin migrar, porque no existen en el respaldo:** `clave_elector` y `voto_id` ("Votó en las elecciones del 2018"). El código legacy los escribe, pero son posteriores al volcado — las columnas no están en `hgo_pachuca.beneficiario`.

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
- [x] **Paridad de UI con el legacy** (2026-08-05), contra `seguridad/index.php` y sus pantallas de etapa:
  - **Una pantalla por etapa**, como el legacy: *Recepción* (`dnncs_edit`), **Toma de conocimiento** (`dnncs_tncn` → `PUT denuncias/{id}/atender`) y **Término** (`dnncs_trmn` → `PUT denuncias/{id}/concluir`). Antes el front las tenía fundidas en un solo formulario.
  - El backend **no deja concluir sin toma de conocimiento**, y la casilla aparece deshabilitada con la razón a la vista.
  - **Filtros del listado**: localidad (selector en cascada), etapa y **rango de fechas**, que no existían.
  - **Ficha del detenido** (`ficha_dtnd.php`), que no estaba: sólo lectura, agrupada en identidad / contacto / retención, omitiendo los campos vacíos.
  - **Corregido**: el buscador de la tabla descartaba los filtros al escribir (faltaba pasarle `extraParams`).
  - **Etapa como riel de tres segmentos** en vez de una etiqueta gris: el flujo es estrictamente secuencial, así que el riel se llena hasta donde llegó el caso y se distingue de un vistazo lo atorado en recepción de lo concluido. Se agregó también la columna con el **texto del reporte**, que es lo que identifica una denuncia.
- [x] Pruebas `tests/Feature/Seguridad/DenunciasTest.php` (6) — filtro por etapa y por rango de fechas, toma de conocimiento, validaciones y la regla de no concluir sin atender.
- [x] **Detenidos con el mismo lenguaje que Directorio**: selector **Tarjetas / Tabla** con la preferencia en `localStorage` (`detenidos:vista`), tarjetas con foto (o monograma), edad y sexo, lugar y fecha de retención, y el motivo al pie. El `Monograma` se movió a `resources/js/components` para compartirlo, y `FotoController` sirve también la foto del detenido (`/directorio/detenidos/{id}/foto.img`). `DataTable` acepta `soloBuscador` para reusar buscador y paginador en las vistas de tarjetas.
- [x] **Corregido: el sexo del detenido salía invertido.** En este módulo el legacy guarda **2 = HOMBRE y 1 = MUJER** (`dtnd_add.php`), al revés que en Directorio; el formulario venía usando la convención del Directorio. El mapeo quedó centralizado en `detenidos/sexo.ts` para que no se vuelva a divergir. Los datos guardados no se tocaron.

- [x] **Mapa** (`dtnd_mapa.php`): en el legacy no es un visor sino un **selector de coordenadas** que se abría desde la denuncia y devolvía lat/long al formulario. Se replicó con **Leaflet + OpenStreetMap** (`components/mapa-punto.tsx`, sin llave de API) dentro de la Toma de conocimiento: clic o arrastre del alfiler fija `latitud`/`longitud`. El alfiler es un `divIcon` porque la imagen que trae Leaflet se pierde al empaquetar con Vite.
- [x] **PDF** de la **denuncia** y de la **ficha del detenido** (`App\Support\FichaPdf` sobre `codedge/laravel-fpdf`), con las secciones del flujo y los campos vacíos omitidos. El texto pasa por `iconv` a ISO-8859-1: FPDF no maneja UTF-8 y sin eso los acentos salen rotos. Rutas `seguridad/denuncias/{id}/pdf` y `seguridad/detenidos/{id}/pdf`, más el ítem "Imprimir PDF" en ambos listados.
- [x] Pruebas `tests/Feature/Seguridad/PdfTest.php` (2).
- [x] **El acta impresa, rediseñada** (`App\Support\ActaPdf` + `FichaPdf`): pensada para fotocopiarse y archivarse. **Bloque de folio** enmarcado arriba a la derecha (folio + etapa, en monoespaciada como los folios de una forma oficial), que se repite como **cabecera de continuación** en las hojas siguientes; **numeración "Página X de N"**; **cierre "FIN DEL ACTA"** para notar si falta una hoja; y **nada de texto gris** — la jerarquía la carga el tamaño, porque el gris se pierde al fotocopiar. Corregido de paso: el pie caía dentro del margen de salto y abría una página de más en cada documento.
- [x] **Georreferencia en la denuncia**: el `MapaPunto` también está en la Recepción, no sólo en la Toma de conocimiento, con los campos de lat/long visibles por si llegan de otra fuente.
- [x] **Tablero de Seguridad** (Módulo 9, cuarto tablero): cifras por etapa, denuncias por etapa, presuntos delitos más frecuentes y **mapa de incidencias** (`tableros/seguridad.tsx` + `mapa-incidencias.tsx`). Permiso `tableros.seguridad`, entrada propia en el menú. El alfiler toma el color de la etapa, el mismo código que el riel del listado de Seguridad.
- [x] En el diálogo de la denuncia **se quitaron los campos de latitud y longitud**: las fija el mapa, capturarlas a mano no aportaba.
- [x] Lo mismo en el **diálogo de la solicitud** (Módulo 3): fuera los inputs de latitud y longitud, y en su lugar un `MapaPunto` **plegado** (`h-56`), porque la coordenada es opcional y la mayoría de las solicitudes no la lleva. Cerrado se ve "Marcar ubicación en el mapa · Opcional"; con punto puesto muestra la coordenada y un "Quitar punto". `MapaPunto` ganó `alto` y `leyenda` para no repetir el texto de denuncias.
- [x] **Diseño de los 4 tableros** (sin tocar datos ni gráficas: son las mismas donas, columnas, barras agrupadas y gauge de Highcharts). Encabezado y separadores de sección iguales en las cuatro pantallas (`Encabezado` / `Seccion`, hoy en `components/pagina.tsx`), y cada panel con una nota que dice qué mide. En las gráficas se corrigieron defectos de lectura: el eje Y decía **"Values"** en inglés y las series de un solo dato traían una leyenda **"Total"** que no informaba nada; las etiquetas de columna ya no se giran 45° ni se cortan (`autoRotation`, se parten en varias líneas); las cifras van con separador de miles en es-MX; y **la dona lleva el total en el hueco**. Las dos donas de Gestión pasaron de un tercio a media pantalla, donde antes quedaban del tamaño de una moneda con las etiquetas encimadas.

**Sin recuperar:** el select **"Turnado"** del término. Su catálogo (`cat_turnado`) no existe en el respaldo de Actopan que se restauró, así que las etiquetas no son recuperables; los valores guardados en `turnado` (0–3) se conservan intactos.
- [x] Páginas React: **Denuncias** (captura + atención + estatus derivado) y **Detenidos** (con vínculo a denuncia). Submenú "Seguridad" + permiso `seguridad.gestionar`.
- [x] 4 catálogos de seguridad editables en la pantalla de Catálogos.

**Nota de fidelidad de datos:** el denunciante y el detenido son **texto libre** en el legacy (no ligan al directorio), por lo que migrar seguridad de Actopan **no mezcla** con el directorio de Pachuca. Es un módulo autocontenido.
