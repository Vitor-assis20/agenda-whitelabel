import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

import { DateStrip, SlotGrid } from '@/components/booking/schedule-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import TenantLayout from '@/layouts/tenant-layout';
import { type AvailableSlot, type BookableDate, type BookedAppointment } from '@/types/booking';

interface RescheduleProps {
    appointment: BookedAppointment;
    dates: BookableDate[];
    selected_date: string | null;
    /** Horários livres do dia escolhido (o horário atual do próprio agendamento não conta como ocupado). */
    slots: AvailableSlot[];
    submit_url: string;
    /** Página do agendamento (link assinado). */
    back_url: string;
}

export default function Reschedule({ appointment, dates, selected_date, slots, submit_url, back_url }: RescheduleProps) {
    const [loading, setLoading] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ starts_at: '' });
    const selectedSlot = slots.find((slot) => slot.starts_at === data.starts_at);

    // O dia vai na URL atual, que mantém a assinatura do link.
    const selectDate = (date: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        setData('starts_at', '');
        router.get(
            url.toString(),
            {},
            {
                only: ['slots', 'selected_date'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(submit_url, {
            preserveScroll: true,
            // Horário tomado no meio do caminho: recarrega a grade do dia.
            onError: () => selected_date && router.reload({ only: ['slots'] }),
        });
    };

    return (
        <TenantLayout>
            <Head title="Remarcar agendamento" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Remarcar agendamento</h1>
                    <p className="text-muted-foreground">
                        {appointment.service} com {appointment.professional} · atual: {appointment.date}, {appointment.time}
                    </p>
                </div>

                <section className="space-y-3">
                    <h2 className="font-semibold">Escolha o novo horário</h2>
                    <DateStrip dates={dates} selected={selected_date} onSelect={selectDate} />
                    {selected_date && (
                        <SlotGrid slots={slots} loading={loading} selected={data.starts_at} onSelect={(startsAt) => setData('starts_at', startsAt)} />
                    )}
                    <InputError message={errors.starts_at} />
                </section>

                <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={!selectedSlot || processing}>
                        {processing && <LoaderCircle className="size-4 animate-spin" />}
                        {selectedSlot ? `Remarcar para as ${selectedSlot.time}` : 'Escolha um horário'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={back_url}>Voltar</Link>
                    </Button>
                </form>
            </div>
        </TenantLayout>
    );
}
