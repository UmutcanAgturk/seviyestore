<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

enum PasswordTokenPurpose: string
{
    /** "Şifremi Unuttum" - user already has a password, wants a new one. */
    case RESET = 'reset';
}
