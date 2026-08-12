<?php

declare(strict_types=1);

namespace Seviye\SubeSiparis\Domain;

/**
 * draft -> submitted -> (rejected | awaiting_payment | completed) -> ...
 *
 * Genel Merkez'in approve() kararı iki farklı sonraki duruma yol açar: talep
 * edilen miktarların TAMAMI ücretsiz kotadan karşılanabiliyorsa doğrudan
 * COMPLETED (ödenecek bir şey yok), aşan bir miktar varsa AWAITING_PAYMENT
 * (WooCommerce siparişi oluşturulur, şube müdürü kart ile öder) - bkz.
 * Http\WooCommercePaymentBridge. AWAITING_PAYMENT'tan COMPLETED'e geçiş
 * yalnızca o WooCommerce siparişinin ödemesi tamamlandığında, WooCommerce'in
 * kendi woocommerce_order_status_changed olayını dinleyerek otomatik olur -
 * bu modül hiçbir ödeme durumunu kendi başına "ödendi" olarak işaretlemez.
 *
 * CANCELLED yalnızca DRAFT/SUBMITTED durumundaki bir siparişi şube
 * müdürünün kendisinin vazgeçmesiyle oluşur - onaylanmış (AWAITING_PAYMENT/
 * COMPLETED) bir sipariş asla iptal edilemez, çünkü ücretsiz kota zaten o
 * anda tüketilmiş sayılır (bkz. Support\BranchOrderSplitCalculator'ın
 * "already consumed" hesaplamasının hangi durumları saydığına dair
 * docblock'u).
 */
enum BranchOrderStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REJECTED = 'rejected';
    case AWAITING_PAYMENT = 'awaiting_payment';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
