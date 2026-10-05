import { SVGAttributes } from 'react';

/**
 * Ícone da plataforma (calendário com check).
 *
 * Desenho em traço: o fill inline anula o "fill-current" que os layouts do
 * starter kit aplicam ao ícone, que preencheria as formas.
 */
export default function AppLogoIcon({ style, ...props }: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            style={{ ...style, fill: 'none' }}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <rect width="18" height="18" x="3" y="4" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
            <path d="m9 16 2 2 4-4" />
        </svg>
    );
}
