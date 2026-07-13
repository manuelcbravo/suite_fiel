import { Head } from '@inertiajs/react';
import { columnChart, groupedBar, pieDonut } from '@/components/charts/builders';
import { HChart } from '@/components/charts/hchart';
import { Panel, StatCard } from '@/components/charts/panel';

type Par = [string, number];
type Matriz = {
    asociacion: { personal: number; comunitaria: number };
    ciudadano: { personal: number; comunitaria: number };
};

export default function Financiero({
    inversionTotal,
    inversionPorTipoBeneficiario,
    topLocalidadInversion,
    origenInversion,
}: {
    inversionTotal: number;
    inversionPorTipoBeneficiario: Matriz;
    topLocalidadInversion: Par[];
    origenInversion: Par[];
}) {
    return (
        <>
            <Head title="Tablero financiero" />
            <div className="space-y-4 p-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <StatCard
                        label="Inversión total gestionada"
                        value={inversionTotal}
                        color="green"
                        money
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Panel title="Inversión por tipo de beneficiario">
                        <HChart
                            options={groupedBar(
                                ['Asociación', 'Ciudadano'],
                                [
                                    inversionPorTipoBeneficiario.asociacion
                                        .personal,
                                    inversionPorTipoBeneficiario.ciudadano
                                        .personal,
                                ],
                                [
                                    inversionPorTipoBeneficiario.asociacion
                                        .comunitaria,
                                    inversionPorTipoBeneficiario.ciudadano
                                        .comunitaria,
                                ],
                                true,
                            )}
                        />
                    </Panel>
                    <Panel title="Origen de inversión">
                        <HChart options={pieDonut(origenInversion)} />
                    </Panel>
                </div>

                <Panel title="Top 10 localidad por inversión">
                    <HChart options={columnChart(topLocalidadInversion, true)} />
                </Panel>
            </div>
        </>
    );
}

Financiero.layout = {
    breadcrumbs: [
        { title: 'Tableros', href: '/tableros/financiero' },
        { title: 'Financiero', href: '/tableros/financiero' },
    ],
};
