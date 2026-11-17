<?php
declare(strict_types=1);

const ORDER_STATUS_LABELS = [
    'nouvelle' => 'Nouvelle',
    'confirmee' => 'Confirmée',
    'livree' => 'Livrée',
    'annulee' => 'Annulée',
];

const ORDER_STOCK_MAX = 4294967295;

function order_status_label(string $status): string
{
    if (!array_key_exists($status, ORDER_STATUS_LABELS)) {
        throw new RuntimeException('Le statut de cette commande est invalide.');
    }

    return ORDER_STATUS_LABELS[$status];
}
