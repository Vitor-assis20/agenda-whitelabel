import { Head } from '@inertiajs/react';
import { Store } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

export default function NoBusiness() {
    return (
        <AppLayout>
            <Head title="Nenhum negócio" />

            <div className="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
                <Store className="text-muted-foreground size-10" />
                <h1 className="text-xl font-semibold">Você ainda não faz parte de nenhum negócio</h1>
                <p className="text-muted-foreground max-w-md">Peça ao responsável pelo negócio para adicionar sua conta à equipe.</p>
            </div>
        </AppLayout>
    );
}
