<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

enum PrivacyRequestStatus: string
{
    /** DELETION only - awaiting Genel Merkez review. */
    case PENDING = 'pending';

    /** EXPORT always lands here immediately; DELETION after approval. */
    case COMPLETED = 'completed';

    /** DELETION only - Genel Merkez declined it. */
    case REJECTED = 'rejected';
}
