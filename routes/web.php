<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Config\CatalogoController;
use App\Http\Controllers\Config\RoleController;
use App\Http\Controllers\Config\UserController;
use App\Http\Controllers\Directorio\BeneficiarioController;
use App\Http\Controllers\Directorio\ComentarioController;
use App\Http\Controllers\Directorio\DirectorioController;
use App\Http\Controllers\Directorio\FotoController;
use App\Http\Controllers\Directorio\OrganizacionController;
use App\Http\Controllers\Directorio\ProveedorController;
use App\Http\Controllers\Directorio\UbicacionController;
use App\Http\Controllers\Gestion\SeguimientoController;
use App\Http\Controllers\Gestion\SolicitudController;
use App\Http\Controllers\Invitaciones\InvitacionController;
use App\Http\Controllers\Notas\NotaController;
use App\Http\Controllers\Notas\NotitaController;
use App\Http\Controllers\Obra\ObraController;
use App\Http\Controllers\Reportes\ReporteController;
use App\Http\Controllers\Seguridad\DenunciaController;
use App\Http\Controllers\Seguridad\DetenidoController;
use App\Http\Controllers\Tableros\TableroController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // El dashboard raíz redirige al primer tablero disponible.
    Route::get('dashboard', function () {
        return redirect()->route('tableros.ejecutivo');
    })->name('dashboard');

    // Tableros (Módulo 9): 3 tableros independientes (sin Obra pública).
    Route::prefix('tableros')->group(function () {
        Route::get('ejecutivo', [TableroController::class, 'ejecutivo'])
            ->middleware('can:tableros.ejecutivo')->name('tableros.ejecutivo');
        Route::get('gestion', [TableroController::class, 'gestion'])
            ->middleware('can:tableros.gestion')->name('tableros.gestion');
        Route::get('financiero', [TableroController::class, 'financiero'])
            ->middleware('can:tableros.financiero')->name('tableros.financiero');
        Route::get('seguridad', [TableroController::class, 'seguridad'])
            ->middleware('can:tableros.seguridad')->name('tableros.seguridad');
        Route::get('obra', [TableroController::class, 'obra'])
            ->middleware('can:tableros.obra')->name('tableros.obra');
    });

    // Administración de usuarios y roles (protegida por permiso).
    Route::prefix('config')->middleware('can:usuarios.gestionar')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('config.users.index');
        Route::post('users', [UserController::class, 'store'])->name('config.users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('config.users.destroy');
        Route::get('roles', [RoleController::class, 'index'])->name('config.roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('config.roles.store');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('config.roles.destroy');
    });

    // Catálogos (Módulo 0): pantalla única con pestañas.
    Route::prefix('config')->middleware('can:catalogos.gestionar')->group(function () {
        Route::get('catalogos', [CatalogoController::class, 'index'])->name('config.catalogos.index');
        Route::post('catalogos/{catalogo}', [CatalogoController::class, 'store'])->name('config.catalogos.store');
        Route::delete('catalogos/{catalogo}/{id}', [CatalogoController::class, 'destroy'])->name('config.catalogos.destroy');
    });

    // Directorio (Módulo 2): una pantalla con tres pestañas (individuos,
    // organizaciones y proveedores), como el legacy.
    Route::prefix('directorio')->middleware('can:directorio.gestionar')->group(function () {
        Route::get('/', [DirectorioController::class, 'index'])->name('directorio.index');

        // Lookups geográficos dependientes (reutilizables).
        Route::get('ubicaciones/municipios', [UbicacionController::class, 'municipios'])->name('directorio.ubicaciones.municipios');
        Route::get('ubicaciones/localidades', [UbicacionController::class, 'localidades'])->name('directorio.ubicaciones.localidades');

        Route::get('beneficiarios/buscar', [BeneficiarioController::class, 'buscar'])->name('directorio.beneficiarios.buscar');
        Route::post('beneficiarios', [BeneficiarioController::class, 'store'])->name('directorio.beneficiarios.store');
        Route::delete('beneficiarios/{beneficiario}', [BeneficiarioController::class, 'destroy'])->name('directorio.beneficiarios.destroy');

        Route::get('organizaciones/buscar', [OrganizacionController::class, 'buscar'])->name('directorio.organizaciones.buscar');
        Route::post('organizaciones', [OrganizacionController::class, 'store'])->name('directorio.organizaciones.store');
        Route::delete('organizaciones/{organizacion}', [OrganizacionController::class, 'destroy'])->name('directorio.organizaciones.destroy');

        Route::post('proveedores', [ProveedorController::class, 'store'])->name('directorio.proveedores.store');
        Route::delete('proveedores/{proveedor}', [ProveedorController::class, 'destroy'])->name('directorio.proveedores.destroy');

        // Las tres antiguas pantallas sueltas ahora son pestañas de una sola.
        foreach (['beneficiarios' => 'individuos', 'organizaciones' => 'organizaciones', 'proveedores' => 'proveedores'] as $ruta => $pestana) {
            Route::get($ruta, fn () => redirect()->route('directorio.index', ['tab' => $pestana]))
                ->name("directorio.{$ruta}.index");
        }

        // Registro completo para precargar el formulario de edición.
        Route::get('{tipo}/{id}/registro', [DirectorioController::class, 'registro'])
            ->whereIn('tipo', ['beneficiarios', 'organizaciones', 'proveedores'])->whereNumber('id')
            ->name('directorio.registro');

        // Comentarios polimórficos (beneficiarios / organizaciones / proveedores).
        Route::get('{tipo}/{id}/comentarios', [ComentarioController::class, 'index'])
            ->whereIn('tipo', ['beneficiarios', 'organizaciones', 'proveedores'])->whereNumber('id')
            ->name('directorio.comentarios.index');
        Route::post('{tipo}/{id}/comentarios', [ComentarioController::class, 'store'])
            ->whereIn('tipo', ['beneficiarios', 'organizaciones', 'proveedores'])->whereNumber('id')
            ->name('directorio.comentarios.store');
        Route::delete('comentarios/{comentario}', [ComentarioController::class, 'destroy'])
            ->name('directorio.comentarios.destroy');

        // Foto (data URI) bajo demanda.
        Route::get('{tipo}/{id}/foto', [FotoController::class, 'show'])
            ->whereIn('tipo', ['beneficiarios', 'organizaciones', 'proveedores'])->whereNumber('id')
            ->name('directorio.foto.show');
        // La misma foto como imagen, para las tarjetas.
        Route::get('{tipo}/{id}/foto.img', [FotoController::class, 'imagen'])
            ->whereIn('tipo', ['beneficiarios', 'organizaciones', 'proveedores', 'detenidos'])->whereNumber('id')
            ->name('directorio.foto.imagen');
    });

    // Gestión (Módulo 3): solicitudes y seguimientos (turnado/respuesta).
    Route::prefix('gestion')->middleware('can:gestion.gestionar')->group(function () {
        Route::get('solicitudes', [SolicitudController::class, 'index'])->name('gestion.solicitudes.index');
        Route::post('solicitudes', [SolicitudController::class, 'store'])->name('gestion.solicitudes.store');
        Route::delete('solicitudes/{solicitud}', [SolicitudController::class, 'destroy'])->name('gestion.solicitudes.destroy');
        // Siguiente tanda de solicitudes de un expediente (listado agrupado).
        Route::get('solicitantes/{tipo}/{id}/solicitudes', [SolicitudController::class, 'solicitudesDe'])
            ->whereIn('tipo', ['beneficiario', 'organizacion'])->whereNumber('id')
            ->name('gestion.solicitantes.solicitudes');
        Route::get('solicitudes/{solicitud}/seguimientos', [SolicitudController::class, 'seguimientos'])->name('gestion.solicitudes.seguimientos');
        Route::post('solicitudes/{solicitud}/seguimientos', [SeguimientoController::class, 'store'])->name('gestion.seguimientos.store');
        Route::get('solicitudes/{solicitud}/verificaciones', [SolicitudController::class, 'verificaciones'])->name('gestion.solicitudes.verificaciones');
        Route::post('solicitudes/{solicitud}/verificaciones', [SolicitudController::class, 'registrarVerificacion'])->name('gestion.verificaciones.store');
        Route::post('solicitudes/{solicitud}/atender', [SolicitudController::class, 'atender'])->name('gestion.solicitudes.atender');
        Route::put('solicitudes/{solicitud}/notificar', [SeguimientoController::class, 'notificar'])->name('gestion.solicitudes.notificar');

        Route::put('seguimientos/{seguimiento}/responder', [SeguimientoController::class, 'responder'])->name('gestion.seguimientos.responder');
        Route::put('seguimientos/{seguimiento}/reasignar', [SeguimientoController::class, 'reasignar'])->name('gestion.seguimientos.reasignar');
        Route::delete('seguimientos/{seguimiento}', [SeguimientoController::class, 'destroy'])->name('gestion.seguimientos.destroy');
    });

    // Agenda (Módulo 4): calendario de eventos (FullCalendar).
    Route::prefix('agenda')->middleware('can:agenda.gestionar')->group(function () {
        Route::get('/', [AgendaController::class, 'index'])->name('agenda.index');
        Route::get('eventos', [AgendaController::class, 'eventos'])->name('agenda.eventos');
        Route::post('eventos', [AgendaController::class, 'store'])->name('agenda.eventos.store');
        Route::put('eventos/{evento}', [AgendaController::class, 'update'])->name('agenda.eventos.update');
        Route::delete('eventos/{evento}', [AgendaController::class, 'destroy'])->name('agenda.eventos.destroy');

        // Notas (checklist) del evento — nota única auto-creada por evento.
        Route::get('eventos/{evento}/notas', [AgendaController::class, 'notas'])->name('agenda.eventos.notas');
        Route::post('eventos/{evento}/notas', [AgendaController::class, 'agregarNota'])->name('agenda.eventos.notas.store');
        Route::put('eventos/notas/{notita}/toggle', [NotitaController::class, 'toggle'])->name('agenda.eventos.notas.toggle');
        Route::delete('eventos/notas/{notita}', [NotitaController::class, 'destroy'])->name('agenda.eventos.notas.destroy');
    });

    // Invitaciones (Módulo 6): vinculables a eventos de la agenda.
    Route::prefix('invitaciones')->middleware('can:invitaciones.gestionar')->group(function () {
        Route::get('eventos/buscar', [AgendaController::class, 'buscar'])->name('invitaciones.eventos.buscar');
        Route::get('/', [InvitacionController::class, 'index'])->name('invitaciones.index');
        Route::post('/', [InvitacionController::class, 'store'])->name('invitaciones.store');
        Route::delete('{invitacion}', [InvitacionController::class, 'destroy'])->name('invitaciones.destroy');
        Route::get('{invitacion}/correos', [InvitacionController::class, 'correos'])->name('invitaciones.correos');
        Route::post('{invitacion}/correos', [InvitacionController::class, 'notificar'])->name('invitaciones.notificar');
    });

    // Notas (Módulo 5): notas con checklist, vinculables a la agenda.
    Route::prefix('notas')->middleware('can:notas.gestionar')->group(function () {
        Route::get('eventos/buscar', [AgendaController::class, 'buscar'])->name('notas.eventos.buscar');
        Route::get('/', [NotaController::class, 'index'])->name('notas.index');
        Route::post('/', [NotaController::class, 'store'])->name('notas.store');
        Route::delete('{nota}', [NotaController::class, 'destroy'])->name('notas.destroy');
        Route::post('{nota}/pendientes', [NotitaController::class, 'store'])->name('notas.pendientes.store');
        Route::put('pendientes/{notita}', [NotitaController::class, 'update'])->name('notas.pendientes.update');
        Route::put('pendientes/{notita}/toggle', [NotitaController::class, 'toggle'])->name('notas.pendientes.toggle');
        Route::delete('pendientes/{notita}', [NotitaController::class, 'destroy'])->name('notas.pendientes.destroy');
    });

    // Obra pública (Módulo 10): expediente de obra por etapas.
    Route::prefix('obra')->middleware('can:obra.gestionar')->group(function () {
        Route::get('solicitudes', [ObraController::class, 'solicitudes'])->name('obra.solicitudes');
        Route::get('proveedores', [ObraController::class, 'proveedores'])->name('obra.proveedores');
        Route::get('/', [ObraController::class, 'index'])->name('obra.index');
        Route::post('/', [ObraController::class, 'store'])->name('obra.store');
        Route::get('{obra}/ficha', [ObraController::class, 'ficha'])->name('obra.ficha');
        Route::get('{obra}/etapas/{etapa}', [ObraController::class, 'etapa'])->name('obra.etapa');
        Route::put('{obra}/etapas/{etapa}', [ObraController::class, 'guardarEtapa'])->name('obra.etapa.guardar');
        Route::get('{obra}/estimaciones', [ObraController::class, 'estimaciones'])->name('obra.estimaciones');
        Route::post('{obra}/estimaciones', [ObraController::class, 'guardarEstimacion'])->name('obra.estimaciones.guardar');
        Route::delete('{obra}/estimaciones/{estimacion}', [ObraController::class, 'eliminarEstimacion'])->name('obra.estimaciones.eliminar');
        Route::delete('{obra}', [ObraController::class, 'destroy'])->name('obra.destroy');
    });

    // Reportes (Módulo 8): submódulos independientes con filtros + Excel.
    Route::prefix('reportes')->group(function () {
        Route::get('seguridad', [ReporteController::class, 'seguridad'])->middleware('can:seguridad.gestionar')->name('reportes.seguridad');
        Route::get('seguridad.xlsx', [ReporteController::class, 'seguridadExcel'])->middleware('can:seguridad.gestionar')->name('reportes.seguridad.excel');
        Route::get('agenda', [ReporteController::class, 'agenda'])->middleware('can:reportes.agenda')->name('reportes.agenda');
        Route::get('agenda.xlsx', [ReporteController::class, 'agendaExcel'])->middleware('can:reportes.agenda')->name('reportes.agenda.excel');
        Route::get('directorio', [ReporteController::class, 'directorio'])->middleware('can:reportes.directorio')->name('reportes.directorio');
        Route::get('directorio.xlsx', [ReporteController::class, 'directorioExcel'])->middleware('can:reportes.directorio')->name('reportes.directorio.excel');
        Route::get('obra', [ReporteController::class, 'obra'])->middleware('can:obra.gestionar')->name('reportes.obra');
        Route::get('obra.xlsx', [ReporteController::class, 'obraExcel'])->middleware('can:obra.gestionar')->name('reportes.obra.excel');
        Route::get('gestion', [ReporteController::class, 'gestion'])->middleware('can:reportes.gestion')->name('reportes.gestion');
        Route::get('gestion.xlsx', [ReporteController::class, 'gestionExcel'])->middleware('can:reportes.gestion')->name('reportes.gestion.excel');
    });

    // Seguridad ciudadana: denuncias y detenidos.
    Route::prefix('seguridad')->middleware('can:seguridad.gestionar')->group(function () {
        Route::get('denuncias/buscar', [DetenidoController::class, 'buscarDenuncias'])->name('seguridad.denuncias.buscar');
        Route::get('denuncias', [DenunciaController::class, 'index'])->name('seguridad.denuncias.index');
        Route::post('denuncias', [DenunciaController::class, 'store'])->name('seguridad.denuncias.store');
        // Flujo del legacy: recepción → toma de conocimiento → término.
        Route::put('denuncias/{denuncia}/atender', [DenunciaController::class, 'atender'])->name('seguridad.denuncias.atender');
        Route::put('denuncias/{denuncia}/concluir', [DenunciaController::class, 'concluir'])->name('seguridad.denuncias.concluir');
        Route::get('denuncias/{denuncia}/pdf', [DenunciaController::class, 'pdf'])->name('seguridad.denuncias.pdf');
        Route::delete('denuncias/{denuncia}', [DenunciaController::class, 'destroy'])->name('seguridad.denuncias.destroy');

        Route::get('detenidos', [DetenidoController::class, 'index'])->name('seguridad.detenidos.index');
        Route::post('detenidos', [DetenidoController::class, 'store'])->name('seguridad.detenidos.store');
        Route::get('detenidos/{detenido}/pdf', [DetenidoController::class, 'pdf'])->name('seguridad.detenidos.pdf');
        Route::delete('detenidos/{detenido}', [DetenidoController::class, 'destroy'])->name('seguridad.detenidos.destroy');
    });

    // Capacitación (Módulo 7): ayuda, manuales y videos (contenido estático).
    Route::inertia('capacitacion', 'capacitacion/index')
        ->middleware('can:capacitacion.ver')
        ->name('capacitacion.index');
});

require __DIR__.'/settings.php';
