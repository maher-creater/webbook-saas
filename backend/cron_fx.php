<?php
/**
 * 2030B P2P Pairing — Daily FX cache refresh.
 *
 * Add to crontab:  0 3 * * * php /var/www/2030b/backend/cron_fx.php
 */
require __DIR__ . '/inc/bootstrap.php';
$data = b30_exchange_rates(true);
echo "FX rates updated. Base=" . $data['base'] . " · " . count($data['rates']) . " currencies.\n";
