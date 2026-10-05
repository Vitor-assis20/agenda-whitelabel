<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot profissional ↔ serviço.
 *
 * Preenche tenant_id a partir do model pai, exigido pelas chaves
 * estrangeiras compostas. Feito aqui, e não com withPivotValue() na
 * relação, porque withPivotValue() também filtra as consultas e, no eager
 * loading, o model base ainda não tem tenant_id.
 */
class ProfessionalService extends Pivot
{
    protected $table = 'professional_service';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $pivot) {
            $pivot->tenant_id ??= $pivot->pivotParent?->tenant_id;
        });
    }
}
