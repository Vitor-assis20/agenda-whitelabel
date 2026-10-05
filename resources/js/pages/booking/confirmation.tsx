import { Head, router, usePage } from '@inertiajs/react';
import { CalendarCheck, CircleCheck, CircleX, LoaderCircle } from 'lucide-react';
import { useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDuration, formatPrice } from '@/lib/format';
import { type BookedAppointment } from '@/types/booking';

interface ConfirmationProps {
    appointment: BookedAppointment;
    /** Acabou de reservar (e não chegou pelo link do e-mail). */
    just_booked: boolean;
}

function heading(appointment: BookedAppointment, justBooked: boolean) {
    if (appointment.status === 'cancelled') return { Icon: CircleX, title: 'Agendamento cancelado', className: 'text-muted-foreground' };
    if (justBooked) return { Icon: CircleCheck, title: 'Agendamento confirmado!', className: 'text-primary' };

    return { Icon: CalendarCheck, title: 'Seu agendamento', className: 'text-primary' };
}

export default function Confirmation({ appointment, just_booked }: ConfirmationProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [cancelling, setCancelling] = useState(false);
    const { Icon, title, className } = heading(appointment, just_booked);
    const firstName = appointment.customer.split(' ')[0];

    const details = [
        ['Serviço', appointment.service],
        ['Profissional', appointment.professional],
        ['Data', appointment.date],
        ['Horário', appointment.time],
        ['Duração', formatDuration(appointment.duration_minutes)],
        ['Valor', formatPrice(appointment.price_cents)],
    ];

    const cancel = () => {
        if (!appointment.cancel_url || !window.confirm('Cancelar este agendamento? O horário será liberado para outra pessoa.')) return;

        router.post(appointment.cancel_url, {}, { onStart: () => setCancelling(true), onFinish: () => setCancelling(false) });
    };

    return (
        <TenantLayout>
            <Head title={title} />

            <div className="bg-background space-y-6 rounded-lg border p-6 text-center">
                <Icon className={`mx-auto size-12 ${className}`} />

                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">
                        {appointment.status === 'cancelled' ? 'O horário foi liberado.' : `Te esperamos lá, ${firstName}.`}
                    </p>
                </div>

                <dl className="grid gap-4 text-left sm:grid-cols-2">
                    {details.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-muted-foreground text-sm">{label}</dt>
                            <dd className="font-medium">{value}</dd>
                        </div>
                    ))}
                </dl>

                <InputError message={errors.appointment} />

                {appointment.cancel_url && (
                    <Button variant="outline" onClick={cancel} disabled={cancelling}>
                        {cancelling && <LoaderCircle className="size-4 animate-spin" />}
                        Cancelar agendamento
                    </Button>
                )}
            </div>
        </TenantLayout>
    );
}
