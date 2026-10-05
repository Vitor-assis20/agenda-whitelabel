import { Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';

import TenantLayout from '@/layouts/tenant-layout';
import { formatDuration, formatPrice } from '@/lib/format';
import { type BookedAppointment } from '@/types/booking';

export default function Confirmation({ appointment }: { appointment: BookedAppointment }) {
    const details = [
        ['Serviço', appointment.service],
        ['Profissional', appointment.professional],
        ['Data', appointment.date],
        ['Horário', appointment.time],
        ['Duração', formatDuration(appointment.duration_minutes)],
        ['Valor', formatPrice(appointment.price_cents)],
    ];

    return (
        <TenantLayout>
            <Head title="Agendamento confirmado" />

            <div className="bg-background space-y-6 rounded-lg border p-6 text-center">
                <CircleCheck className="text-primary mx-auto size-12" />

                <div>
                    <h1 className="text-2xl font-semibold">Agendamento confirmado!</h1>
                    <p className="text-muted-foreground">Te esperamos lá, {appointment.customer.split(' ')[0]}.</p>
                </div>

                <dl className="grid gap-4 text-left sm:grid-cols-2">
                    {details.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-muted-foreground text-sm">{label}</dt>
                            <dd className="font-medium">{value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </TenantLayout>
    );
}
