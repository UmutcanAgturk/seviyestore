<?php

declare(strict_types=1);

namespace Seviye\Destek\Domain;

enum TicketStatus: string
{
    /** Yeni oluşturuldu, henüz personel yanıtı yok. */
    case OPEN = 'open';

    /** Personel en az bir kez yanıtladı, veli kapanışı bekleniyor değil - süreç devam ediyor. */
    case ANSWERED = 'answered';

    /** Personel kapattı. Veli yeni bir mesaj eklerse tekrar OPEN'a döner - bkz. SupportTicketRepositoryInterface::addMessage(). */
    case CLOSED = 'closed';
}
