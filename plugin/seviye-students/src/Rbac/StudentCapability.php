<?php

declare(strict_types=1);

namespace Seviye\Students\Rbac;

/**
 * Granted to Core roles via {@see \Seviye\Core\Rbac\RbacManager} from
 * {@see \Seviye\Students\StudentsModule::boot()}.
 *
 * Note there is only one "manage" capability, not a separate HQ-only vs.
 * branch-only pair: Genel Merkez, Bölge Müdürü and Şube Müdürü all hold
 * scp_manage_students. The scoping itself (all branches vs. just one) is
 * decided at request time in {@see \Seviye\Students\Http\StudentsRestController}
 * by asking Branches' {@see \Seviye\Branches\Contracts\BranchMembershipInterface}
 * whether the current user is assigned to a branch at all - a WP capability
 * check alone cannot express "your own branch only".
 */
enum StudentCapability: string
{
    case MANAGE_STUDENTS = 'scp_manage_students';

    /**
     * Rehberlik: read-only access to their own branch's students (same
     * branch-scoping as MANAGE_STUDENTS, see StudentsRestController's
     * canAccessStudent()/canViewStudent()) - never create/edit/delete a
     * student or touch parent links.
     */
    case VIEW_STUDENTS = 'scp_view_students';

    /** Veli: see only the students linked to their own account. */
    case VIEW_OWN_CHILDREN = 'scp_view_own_children';
}
