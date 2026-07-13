import { Head } from '@inertiajs/react';
import {
    BookOpen,
    ChevronDown,
    FileText,
    GraduationCap,
    Video,
} from 'lucide-react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

const FAQS: { pregunta: string; respuesta: string }[] = [
    {
        pregunta: '¿Cómo registro un beneficiario?',
        respuesta:
            'Ve a Directorio › Beneficiarios y usa el botón "Nuevo". Captura los datos personales, domicilio (estado, municipio y localidad se cargan en cascada) y guarda.',
    },
    {
        pregunta: '¿Cómo turno una solicitud a una dependencia?',
        respuesta:
            'En Gestión › Solicitudes, abre "Ver / turnar" en la solicitud, elige la dependencia y el área, escribe la instrucción y pulsa "Turnar". Después puedes registrar la respuesta del área.',
    },
    {
        pregunta: '¿Cómo agrego un evento a la agenda?',
        respuesta:
            'En Agenda, haz clic en un día del calendario para crear un evento, o clic sobre un evento para editarlo. También puedes arrastrarlo para reprogramarlo.',
    },
    {
        pregunta: '¿Cómo vinculo una invitación a un evento?',
        respuesta:
            'Al crear o editar una invitación, usa el buscador "Vincular a evento de agenda" y selecciona el evento correspondiente.',
    },
    {
        pregunta: '¿Cómo exporto información a Excel?',
        respuesta:
            'Entra a Reportes y descarga el Excel del padrón de beneficiarios, las solicitudes o la agenda.',
    },
];

export default function CapacitacionIndex() {
    return (
        <>
            <Head title="Capacitación" />
            <div className="space-y-4 p-4">
                <div className="flex items-center gap-3 rounded-xl border border-sidebar-border/70 bg-sidebar-accent/20 p-4">
                    <GraduationCap className="size-5 text-primary" />
                    <div>
                        <h1 className="text-xl font-semibold">Capacitación</h1>
                        <p className="text-sm text-muted-foreground">
                            Preguntas frecuentes, manuales y videos.
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <BookOpen className="size-4 text-primary" /> Preguntas
                        frecuentes
                    </h2>
                    <div className="space-y-2">
                        {FAQS.map((faq) => (
                            <Collapsible
                                key={faq.pregunta}
                                className="rounded-md border"
                            >
                                <CollapsibleTrigger className="group flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm font-medium">
                                    {faq.pregunta}
                                    <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="px-3 pb-3 text-sm text-muted-foreground">
                                    {faq.respuesta}
                                </CollapsibleContent>
                            </Collapsible>
                        ))}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <FileText className="size-4 text-primary" /> Manuales
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Aún no hay manuales cargados. Próximamente se podrán
                            adjuntar documentos PDF por módulo.
                        </p>
                    </div>
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <h2 className="mb-2 flex items-center gap-2 text-sm font-semibold">
                            <Video className="size-4 text-primary" /> Videos
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Aún no hay videotutoriales. Próximamente se podrán
                            enlazar videos de capacitación.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

CapacitacionIndex.layout = {
    breadcrumbs: [{ title: 'Capacitación', href: '/capacitacion' }],
};
