import { LoaderCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { type AvailableSlot, type BookableDate } from '@/types/booking';

/** Faixa rolável com os dias em que dá para agendar. */
export function DateStrip({ dates, selected, onSelect }: { dates: BookableDate[]; selected: string | null; onSelect: (date: string) => void }) {
    return (
        <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2">
            {dates.map((day) => (
                <button
                    key={day.value}
                    type="button"
                    onClick={() => onSelect(day.value)}
                    aria-pressed={day.value === selected}
                    className={cn(
                        'flex w-16 shrink-0 flex-col items-center rounded-lg border py-2 transition',
                        day.value === selected ? 'bg-primary text-primary-foreground border-primary' : 'bg-background hover:border-primary/50',
                    )}
                >
                    <span className="text-xs">{day.weekday}</span>
                    <span className="text-lg font-semibold">{day.day}</span>
                    <span className="text-xs">{day.month}</span>
                </button>
            ))}
        </div>
    );
}

/** Grade de horários livres do dia escolhido. */
export function SlotGrid({
    slots,
    loading,
    selected,
    onSelect,
}: {
    slots: AvailableSlot[];
    loading: boolean;
    selected: string;
    onSelect: (startsAt: string) => void;
}) {
    if (loading) return <LoaderCircle className="text-muted-foreground size-5 animate-spin" />;

    if (slots.length === 0) return <p className="text-muted-foreground text-sm">Nenhum horário livre neste dia. Tente outra data.</p>;

    return (
        <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
            {slots.map((slot) => (
                <Button
                    key={slot.starts_at}
                    type="button"
                    size="sm"
                    variant={slot.starts_at === selected ? 'default' : 'outline'}
                    onClick={() => onSelect(slot.starts_at)}
                >
                    {slot.time}
                </Button>
            ))}
        </div>
    );
}
