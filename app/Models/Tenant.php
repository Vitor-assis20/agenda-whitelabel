<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantScope;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'branding',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Disco onde ficam os arquivos públicos do tenant (logo).
     */
    public const ASSETS_DISK = 'public';

    /**
     * Marca no formato consumido pelo front.
     *
     * O banco guarda o caminho do logo, não a URL: trocar de domínio ou de
     * disco (ex.: S3) não deixa URLs antigas gravadas.
     *
     * @return array{primary_color: string|null, logo_url: string|null}
     */
    public function publicBranding(): array
    {
        $logoPath = $this->branding['logo_path'] ?? null;

        return [
            'primary_color' => $this->branding['primary_color'] ?? null,
            'logo_url' => $logoPath ? Storage::disk(self::ASSETS_DISK)->url($logoPath) : null,
        ];
    }

    /**
     * Executa o callback com este tenant como contexto atual.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        return app(CurrentTenant::class)->run($this, $callback);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleOf(User $user): ?TenantRole
    {
        $role = $this->users()->whereKey($user->getKey())->first()?->pivot->role;

        return $role ? TenantRole::from($role) : null;
    }

    // As relações abaixo já filtram por tenant_id, então dispensam o
    // TenantScope e funcionam mesmo fora de um contexto de tenant.

    /**
     * @return HasMany<Professional, $this>
     */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->withoutGlobalScope(TenantScope::class);
    }
}
