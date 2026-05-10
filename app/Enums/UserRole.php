<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPERADMIN = 'superadmin';
    case OWNER      = 'owner';
    case ADMIN      = 'admin';
    case CASHIER    = 'cashier';
}
