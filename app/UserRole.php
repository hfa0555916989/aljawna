<?php

declare(strict_types=1);

namespace App;

/**
 * أدوار المستخدمين في النظام (القسم 2 و9 من docs/SPEC.md).
 */
enum UserRole: string
{
    case User = 'user';
    case Supervisor = 'supervisor';
    case Admin = 'admin';
}
