<?php

declare(strict_types=1);

namespace Seviye\Core\Rbac;

/**
 * The nine platform-wide roles defined by the Seviye Commerce Platform
 * product specification. Module-specific fine-grained permissions are
 * expressed as {@see Capability}-style values granted to these roles via
 * {@see RbacManager::grantCapability()} - the role set itself is closed and
 * owned by Core.
 */
enum Role: string
{
    case GENEL_MERKEZ = 'scp_genel_merkez';
    case BOLGE_MUDURU = 'scp_bolge_muduru';
    case SUBE_MUDURU = 'scp_sube_muduru';
    case MUHASEBE = 'scp_muhasebe';
    case DEPO = 'scp_depo';
    case SATIS_DANISMANI = 'scp_satis_danismani';
    case REHBERLIK = 'scp_rehberlik';
    case VELI = 'scp_veli';
    case SISTEM = 'scp_sistem';

    public function label(): string
    {
        // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- PHPCompatibility does not yet recognise $this inside a native PHP 8.1+ enum method as object context.
        return match ($this) {
            self::GENEL_MERKEZ => __('Genel Merkez', 'seviye-core'),
            self::BOLGE_MUDURU => __('Bölge Müdürü', 'seviye-core'),
            self::SUBE_MUDURU => __('Şube Müdürü', 'seviye-core'),
            self::MUHASEBE => __('Muhasebe', 'seviye-core'),
            self::DEPO => __('Depo', 'seviye-core'),
            self::SATIS_DANISMANI => __('Satış Danışmanı', 'seviye-core'),
            self::REHBERLIK => __('Rehberlik', 'seviye-core'),
            self::VELI => __('Veli', 'seviye-core'),
            self::SISTEM => __('Sistem', 'seviye-core'),
        };
    }
}
