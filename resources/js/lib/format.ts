const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function formatPrice(cents: number): string {
    return currency.format(cents / 100);
}

/** 5000 → "50,00" (valor para campos de preço). */
export function centsToInput(cents: number): string {
    return (cents / 100).toFixed(2).replace('.', ',');
}

/** "1.250,50" → 125050. Texto inválido vira -1 e é recusado pelo servidor. */
export function inputToCents(value: string): number {
    const normalized = value.trim().replace(/\./g, '').replace(',', '.');
    const amount = Number(normalized);

    return normalized === '' || Number.isNaN(amount) ? -1 : Math.round(amount * 100);
}

export const WEEKDAYS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

export function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) return `${rest} min`;

    return rest === 0 ? `${hours}h` : `${hours}h${String(rest).padStart(2, '0')}`;
}
