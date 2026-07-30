<?php

declare(strict_types=1);

namespace Seviye\Notifications\Domain;

/**
 * Every notification is inserted as PENDING
 * ({@see \Seviye\Notifications\Repository\NotificationRepositoryInterface::record()})
 * and then moved to SENT or FAILED by the dispatcher, uniformly across all
 * three channels - even PANEL, whose channel adapter has no real delivery
 * step to fail at (the row itself IS the delivery) and so always reports
 * success, moving straight to SENT. FAILED therefore only ever actually
 * happens for EMAIL/SMS, when the underlying transport
 * ({@see \Seviye\Notifications\Channel\ChannelInterface}) reports a
 * delivery error.
 */
enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
