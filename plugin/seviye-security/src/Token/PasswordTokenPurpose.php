<?php

declare(strict_types=1);

namespace Seviye\Security\Token;

enum PasswordTokenPurpose: string
{
    /** "Şifremi Unuttum" - user already has a password, wants a new one. */
    case RESET = 'reset';

    /** "İlk Şifre Oluştur" - account exists (created by HQ/branch staff) but has never had a password set. */
    case FIRST_SETUP = 'first_setup';
}
