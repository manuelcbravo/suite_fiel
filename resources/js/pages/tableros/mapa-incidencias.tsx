import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';

export type Punto = {
    id: number;
    lat: number;
    lng: number;
    resumen: string;
    etapa: string;
};

/**
 * Mapa de incidencias del tablero: dónde están ocurriendo las denuncias.
 *
 * El alfiler toma el color de la etapa, el mismo código que el riel del
 * listado de Seguridad — así el tablero y el módulo se leen igual. Va con
 * `divIcon` porque la imagen que trae Leaflet se pierde al empaquetar con Vite.
 */
const COLOR_ETAPA: Record<string, string> = {
    Recibida: '#94a3b8',
    Turnada: '#94a3b8',
    'En atención': '#3b82f6',
    Concluida: '#10b981',
    // Etapas de obra pública, con la misma lógica de color:
    // gris lo que no arranca, azul lo que va en curso, verde lo cerrado.
    'Sin adjudicar': '#94a3b8',
    'En ejecución': '#3b82f6',
    Entregada: '#10b981',
};

function alfiler(etapa: string): L.DivIcon {
    const color = COLOR_ETAPA[etapa] ?? '#94a3b8';

    return L.divIcon({
        className: '',
        // Alfiler con punta: en un mapa con decenas de incidencias encimadas,
        // un círculo no deja ver a qué punto exacto corresponde cada una.
        html: `
            <svg width="22" height="30" viewBox="0 0 30 40" fill="none"
                 xmlns="http://www.w3.org/2000/svg"
                 style="filter:drop-shadow(0 1px 2px rgba(0,0,0,.5))">
                <path d="M15 39c0 0 12-15.5 12-24A12 12 0 1 0 3 15c0 8.5 12 24 12 24z"
                      fill="${color}" stroke="#fff" stroke-width="2.5"/>
                <circle cx="15" cy="14.5" r="4" fill="#fff"/>
            </svg>`,
        iconSize: [22, 30],
        iconAnchor: [11, 29],
    });
}

/** Encuadra el mapa a todos los puntos cada vez que cambia el filtro. */
function Encuadrar({ puntos }: { puntos: Punto[] }) {
    const map = useMap();

    useEffect(() => {
        if (puntos.length === 0) {
            return;
        }

        map.fitBounds(
            L.latLngBounds(puntos.map((p) => [p.lat, p.lng] as [number, number])),
            { padding: [30, 30], maxZoom: 16 },
        );
    }, [map, puntos]);

    return null;
}

export function MapaIncidencias({
    puntos,
    etapas = ['Recibida', 'En atención', 'Concluida'],
    sustantivo = 'incidencia',
    vacio = 'Todavía no hay incidencias ubicadas. Se georreferencian al capturar la denuncia o en la toma de conocimiento.',
}: {
    puntos: Punto[];
    /** Qué etapas dibuja la leyenda; cambian entre denuncias y obras. */
    etapas?: string[];
    sustantivo?: string;
    vacio?: string;
}) {
    if (puntos.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-sidebar-border/70 px-6 py-10 text-center text-sm text-muted-foreground">
                {vacio}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="h-80 overflow-hidden rounded-xl border border-sidebar-border/70">
                <MapContainer
                    center={[puntos[0].lat, puntos[0].lng]}
                    zoom={13}
                    scrollWheelZoom={false}
                    className="size-full"
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <Encuadrar puntos={puntos} />
                    {puntos.map((p) => (
                        <Marker
                            key={p.id}
                            position={[p.lat, p.lng]}
                            icon={alfiler(p.etapa)}
                        >
                            <Popup>
                                <span className="text-xs font-semibold">
                                    #{p.id} · {p.etapa}
                                </span>
                                <br />
                                <span className="text-xs">{p.resumen}</span>
                            </Popup>
                        </Marker>
                    ))}
                </MapContainer>
            </div>

            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                <span>
                    {puntos.length} {sustantivo}
                    {puntos.length === 1 ? ' ubicada' : 's ubicadas'}
                </span>
                {etapas.map((etapa) => (
                    <span key={etapa} className="flex items-center gap-1.5">
                        <span
                            className="size-2.5 rounded-full"
                            style={{ background: COLOR_ETAPA[etapa] }}
                        />
                        {etapa}
                    </span>
                ))}
            </div>
        </div>
    );
}
