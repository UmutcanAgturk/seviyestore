<?php

declare(strict_types=1);

namespace Seviye\Destek\Rbac;

enum SupportCapability: string
{
    /** Veli: kendi ticket'larını oluşturur/görür/yanıtlar. */
    case SUBMIT_TICKET = 'scp_submit_support_ticket';

    /**
     * Personel: ticket kuyruğunu görür, yanıtlar, kapatır. Şube bazlı
     * kapsam BranchMembershipInterface::branchIdForUser() ile aynı ilke -
     * bkz. StudentCapability::MANAGE_STUDENTS'ın dokümantasyonu.
     */
    case MANAGE_TICKETS = 'scp_manage_support_tickets';
}
