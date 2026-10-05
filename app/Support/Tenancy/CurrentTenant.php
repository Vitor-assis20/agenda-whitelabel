<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * Guarda o tenant ativo do request/job atual.
 *
 * Registrado como binding "scoped", então é descartado ao fim de cada
 * request ou job e nunca vaza contexto entre execuções.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Executa o callback no contexto do tenant e restaura o contexto anterior.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
