<?php

declare(strict_types=1);

namespace Seviye\Parents\Repository;

use Seviye\Core\Database\ConnectionInterface;
use Seviye\Parents\Domain\NotificationPreference;
use Seviye\Parents\Domain\ParentProfile;

final class WpdbParentProfileRepository implements ParentProfileRepositoryInterface
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function findByUserId(int $userId): ?ParentProfile
    {
        $table = $this->connection->table('parent_profiles');
        $sql = $this->connection->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", [$userId]);

        $rows = $this->connection->getResults($sql);

        return isset($rows[0]) ? $this->hydrate($rows[0]) : null;
    }

    public function upsert(
        int $userId,
        ?string $phone,
        NotificationPreference $notificationPreference,
        bool $kvkkConsentGiven
    ): ParentProfile {
        $existing = $this->findByUserId($userId);
        $table = $this->connection->table('parent_profiles');
        $now = $this->now();
        $kvkkConsentAt = $this->resolveConsentTimestamp($existing?->kvkkConsentAt, $kvkkConsentGiven, $now);

        if ($existing === null) {
            $this->connection->insert($table, [
                'user_id' => $userId,
                'phone' => $phone,
                'notification_preference' => $notificationPreference->value,
                'kvkk_consent_at' => $kvkkConsentAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $sql = $this->connection->prepare(
                'UPDATE ' . $table . ' SET phone = %s, notification_preference = %s, '
                    . 'kvkk_consent_at = %s, updated_at = %s WHERE user_id = %d',
                [$phone, $notificationPreference->value, $kvkkConsentAt, $now, $userId]
            );
            $this->connection->query($sql);
        }

        return new ParentProfile($userId, $phone, $notificationPreference, $kvkkConsentAt);
    }

    private function resolveConsentTimestamp(?string $existingConsentAt, bool $consentGiven, string $now): ?string
    {
        if ($existingConsentAt !== null) {
            return $existingConsentAt;
        }

        return $consentGiven ? $now : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ParentProfile
    {
        return new ParentProfile(
            (int) $row['user_id'],
            $row['phone'] !== null && $row['phone'] !== '' ? (string) $row['phone'] : null,
            NotificationPreference::from((string) $row['notification_preference']),
            $row['kvkk_consent_at'] !== null && $row['kvkk_consent_at'] !== '' ? (string) $row['kvkk_consent_at'] : null
        );
    }

    private function now(): string
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
