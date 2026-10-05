export interface Tenant {
    name: string;
    slug: string;
    branding: {
        primary_color?: string | null;
        logo_url?: string | null;
    };
}

export interface BookableProfessional {
    id: number;
    name: string;
}

export interface BookableService {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price_cents: number;
    professionals: BookableProfessional[];
}

export interface BookableDate {
    value: string;
    weekday: string;
    day: string;
    month: string;
}

export interface AvailableSlot {
    /** Instante em UTC (ISO 8601), enviado de volta na reserva. */
    starts_at: string;
    /** Horário local do negócio, para exibição. */
    time: string;
}

export interface BookedAppointment {
    service: string;
    professional: string;
    customer: string;
    date: string;
    time: string;
    duration_minutes: number;
    price_cents: number;
    status: 'pending' | 'confirmed' | 'cancelled' | 'completed' | 'no_show';
    /** URL assinada para cancelar; null quando o cliente não pode mais cancelar. */
    cancel_url: string | null;
}
