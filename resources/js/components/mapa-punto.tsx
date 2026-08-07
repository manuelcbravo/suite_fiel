import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect } from 'react';
import { MapContainer, Marker, TileLayer, useMap, useMapEvents } from 'react-leaflet';

/**
 * Selector de un punto en el mapa, equivalente al `dtnd_mapa.php` del legacy
 * (allá era Google Maps y se arrastraba el centro; aquí se pone un alfiler).
 *
 * Usa OpenStreetMap, así que no necesita llave de API. El alfiler se dibuja con
 * un `divIcon` en vez de la imagen que trae Leaflet, porque esa imagen se
 * pierde al empaquetar con Vite y además así respeta el color del tema.
 */
const ALFILER = L.divIcon({
    className: '',
    // Alfiler de verdad (con punta y sombra en el suelo) en vez de un punto:
    // así se ve exactamente qué coordenada quedó marcada.
    html: `
        <span style="position:relative;display:block;width:30px;height:40px">
            <svg width="30" height="40" viewBox="0 0 30 40" fill="none"
                 xmlns="http://www.w3.org/2000/svg"
                 style="filter:drop-shadow(0 2px 3px rgba(0,0,0,.45))">
                <path d="M15 39c0 0 12-15.5 12-24A12 12 0 1 0 3 15c0 8.5 12 24 12 24z"
                      fill="#e11d48" stroke="#fff" stroke-width="2.5"/>
                <circle cx="15" cy="14.5" r="4.5" fill="#fff"/>
            </svg>
        </span>`,
    iconSize: [30, 40],
    // La punta del alfiler es la que marca el punto, no su centro.
    iconAnchor: [15, 39],
});

/** Centro por omisión cuando el registro no trae coordenadas: Actopan, Hgo. */
const CENTRO_POR_OMISION: [number, number] = [20.2686, -98.9439];

function Clicks({ onChange }: { onChange: (lat: number, lng: number) => void }) {
    useMapEvents({
        click: (e) => onChange(e.latlng.lat, e.latlng.lng),
    });

    return null;
}

/** Recentra el mapa cuando las coordenadas cambian desde fuera. */
function Recentrar({ punto }: { punto: [number, number] | null }) {
    const map = useMap();

    useEffect(() => {
        if (punto) {
            map.setView(punto, map.getZoom());
        }
    }, [map, punto]);

    return null;
}

export function MapaPunto({
    latitud,
    longitud,
    onChange,
    /** Alto del mapa; se baja donde el diálogo va apretado. */
    alto = 'h-64',
    /** Qué se está marcando, para que la instrucción no hable de otra cosa. */
    leyenda = 'dónde ocurrió',
}: {
    latitud: string;
    longitud: string;
    onChange: (latitud: string, longitud: string) => void;
    alto?: string;
    leyenda?: string;
}) {
    const lat = Number.parseFloat(latitud);
    const lng = Number.parseFloat(longitud);
    const punto: [number, number] | null =
        Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;

    const mover = (nuevaLat: number, nuevaLng: number) =>
        onChange(nuevaLat.toFixed(7), nuevaLng.toFixed(7));

    return (
        <div className="space-y-2">
            <div className={`${alto} overflow-hidden rounded-lg border`}>
                <MapContainer
                    center={punto ?? CENTRO_POR_OMISION}
                    zoom={punto ? 16 : 13}
                    scrollWheelZoom={false}
                    className="size-full"
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <Clicks onChange={mover} />
                    <Recentrar punto={punto} />
                    {punto && (
                        <Marker
                            position={punto}
                            icon={ALFILER}
                            draggable
                            eventHandlers={{
                                dragend: (e) => {
                                    const p = e.target.getLatLng();
                                    mover(p.lat, p.lng);
                                },
                            }}
                        />
                    )}
                </MapContainer>
            </div>

            <p className="text-xs text-muted-foreground">
                {punto ? (
                    <>
                        Punto en{' '}
                        <span className="tabular-nums">
                            {punto[0].toFixed(5)}, {punto[1].toFixed(5)}
                        </span>
                        . Arrastra el alfiler o vuelve a hacer clic para
                        corregirlo.
                    </>
                ) : (
                    `Haz clic en el mapa para marcar ${leyenda}.`
                )}
            </p>
        </div>
    );
}
