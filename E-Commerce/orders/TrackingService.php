<?php

/**
 * @param array<string, mixed> $order
 * @return array<int, array{key: string, label_key: string, done: bool, active: bool, date: ?string}>
 */
function build_order_tracking_timeline(array $order, string $lang = 'tr'): array
{
    $status = strtolower((string) ($order['status'] ?? 'pending'));
    $paymentStatus = function_exists('normalize_order_payment_status')
        ? normalize_order_payment_status((string) ($order['payment_status'] ?? 'paid'))
        : strtolower((string) ($order['payment_status'] ?? 'paid'));

    $createdAt = format_tracking_date((string) ($order['created_at'] ?? ''));
    $shippedAt = format_tracking_date((string) ($order['shipped_at'] ?? ''));
    $deliveredAt = format_tracking_date((string) ($order['delivered_at'] ?? ''));

    $isPaid = in_array($paymentStatus, ['paid'], true);
    $isShipped = in_array($status, ['shipped', 'delivered'], true);
    $isDelivered = $status === 'delivered';
    $isCancelled = $status === 'cancelled';

    if ($isCancelled) {
        return [
            ['key' => 'placed', 'label_key' => 'orders.tracking_placed', 'done' => true, 'active' => false, 'date' => $createdAt],
            ['key' => 'cancelled', 'label_key' => 'orders.status.cancelled', 'done' => true, 'active' => true, 'date' => null],
        ];
    }

    return [
        [
            'key' => 'placed',
            'label_key' => 'orders.tracking_placed',
            'done' => true,
            'active' => false,
            'date' => $createdAt,
        ],
        [
            'key' => 'preparing',
            'label_key' => 'orders.tracking_preparing',
            'done' => $isPaid,
            'active' => $isPaid && !$isShipped,
            'date' => $isPaid && !$isShipped ? $createdAt : null,
        ],
        [
            'key' => 'shipped',
            'label_key' => 'orders.status.shipped',
            'done' => $isShipped,
            'active' => $isShipped && !$isDelivered,
            'date' => $shippedAt,
        ],
        [
            'key' => 'delivered',
            'label_key' => 'orders.status.delivered',
            'done' => $isDelivered,
            'active' => $isDelivered,
            'date' => $deliveredAt,
        ],
    ];
}

function format_tracking_date(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    return date('d.m.Y H:i', $ts);
}

function estimate_delivery_date(array $order): ?string
{
    $status = strtolower((string) ($order['status'] ?? 'pending'));
    if ($status === 'delivered' && !empty($order['delivered_at'])) {
        return format_tracking_date((string) $order['delivered_at']);
    }
    if ($status === 'cancelled') {
        return null;
    }

    $base = (string) ($order['shipped_at'] ?? $order['created_at'] ?? '');
    $ts = strtotime($base);
    if ($ts === false) {
        return null;
    }

    $daysToAdd = $status === 'shipped' ? 2 : 4;
    $eta = strtotime('+' . $daysToAdd . ' weekdays', $ts);
    return $eta !== false ? date('d.m.Y', $eta) : null;
}

function tracking_external_url(?string $carrier, string $trackingNumber): ?string
{
    $trackingNumber = trim($trackingNumber);
    if ($trackingNumber === '') {
        return null;
    }

    $carrierKey = strtolower(trim((string) $carrier));
    $encoded = rawurlencode($trackingNumber);

    $patterns = [
        'yurtiçi' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $encoded,
        'yurtici' => 'https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code=' . $encoded,
        'aras' => 'https://www.araskargo.com.tr/trtt.aspx?code=' . $encoded,
        'mng' => 'https://service.mngkargo.com.tr/iactive/popup/kargotakip.aspx?k=' . $encoded,
        'ptt' => 'https://gonderitakip.ptt.gov.tr/Track/Verify?q=' . $encoded,
        'ups' => 'https://www.ups.com/track?tracknum=' . $encoded,
        'dhl' => 'https://www.dhl.com/tr-tr/home/tracking.html?tracking-id=' . $encoded,
    ];

    foreach ($patterns as $needle => $url) {
        if ($carrierKey !== '' && str_contains($carrierKey, $needle)) {
            return $url;
        }
    }

    if (preg_match('/^ZR[A-Z0-9]+$/i', $trackingNumber)) {
        return null;
    }

    return 'https://www.google.com/search?q=' . rawurlencode($trackingNumber . ' kargo takip');
}

function generate_demo_tracking_number(int $orderId): string
{
    return 'ZERA' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT) . strtoupper(bin2hex(random_bytes(2)));
}
