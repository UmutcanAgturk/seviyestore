<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

/**
 * One KVKK talebi (export or deletion) - see PrivacyRequestType/
 * PrivacyRequestStatus for the two axes, and
 * Http\PrivacyRequestsRestController for the workflow this record moves
 * through.
 */
final class PrivacyRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly PrivacyRequestType $type,
        public readonly PrivacyRequestStatus $status,
        public readonly ?string $note,
        public readonly ?string $resolutionNote,
        public readonly string $requestedAt,
        public readonly ?string $resolvedAt,
        public readonly ?int $resolvedBy
    ) {
    }
}
