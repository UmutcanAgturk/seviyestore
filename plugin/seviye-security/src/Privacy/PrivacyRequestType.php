<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

/**
 * KVKK ("Kişisel Verilerin Korunması Kanunu") - a data subject's two basic
 * rights this platform supports: read what is held about them (EXPORT), or
 * ask for it to be anonymized (DELETION). See
 * Http\PrivacyRequestsRestController's class docblock for why EXPORT
 * completes instantly (self-service) while DELETION only ever queues a
 * request for Genel Merkez review.
 */
enum PrivacyRequestType: string
{
    case EXPORT = 'export';
    case DELETION = 'deletion';
}
