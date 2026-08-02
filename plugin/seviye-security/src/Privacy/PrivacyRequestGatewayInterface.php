<?php

declare(strict_types=1);

namespace Seviye\Security\Privacy;

interface PrivacyRequestGatewayInterface
{
    /**
     * @return int the new request's id
     */
    public function create(int $userId, PrivacyRequestType $type, PrivacyRequestStatus $status, ?string $note): int;

    public function find(int $id): ?PrivacyRequest;

    /**
     * @return list<PrivacyRequest> newest first
     */
    public function forUser(int $userId): array;

    /**
     * @return list<PrivacyRequest> every PENDING request, oldest first -
     *     Genel Merkez's review queue
     */
    public function pending(): array;

    public function resolve(int $id, PrivacyRequestStatus $status, ?string $resolutionNote, int $resolvedBy): void;
}
