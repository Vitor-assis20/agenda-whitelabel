<?php

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function canManageTenant(): bool
    {
        return $this !== self::Staff;
    }
}
