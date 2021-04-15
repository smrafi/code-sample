<?php
/**
 *
 * @author          Mohamed Rafi <me@smrafi.net>
 * @since           PHP 7
 *
 */

define('SHORTINIT', true);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../wp-load.php';

date_default_timezone_set('Asia/Colombo');

use Automattic\WooCommerce\Client;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

global $wpdb;

$consumerWebKey = 'xxxxxxxxxxxxxxxx';
$consumerWebSecret = 'cs_xxxxxxxx';
$siteWeb = 'https://www.eshandlooms.com';

$consumerPosKey = 'ck_xxxxxxx';
$consumerPosSecret = 'cs_xxxxxx';
$sitePos = 'http://pos.eshandlooms.com';

$log = new Logger('posSync');
$log->pushHandler(new StreamHandler('logs/website-sync.log', Logger::DEBUG));

$woocommercePos = new Client(
    $sitePos,
    $consumerPosKey,
    $consumerPosSecret,
    [
        'wp_api' => true,
        'version' => 'wc/v3',
        'timeout' => 1000,
        'ssl_verify' => false,
    ]
);

$woocommerceWeb = new Client(
    $siteWeb,
    $consumerWebKey,
    $consumerWebSecret,
    [
        'wp_api' => true,
        'version' => 'wc/v3',
        'timeout' => 1000,
        'ssl_verify' => true,
    ]
);

try {
    $params = [
        'status' => 'processing, on-hold',
        'after' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ];

    $orders = $woocommerceWeb->get('orders', $params);

    // Get the completed order in less than a min
    if (!empty($orders)) {
        foreach ($orders as $order) {
            // Check whether order is already processed or not
            $processed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM es_sync_website_to_pos WHERE order_id = %d AND processed = %d",
                    [$order->id, 1])
            );

            if (!$processed) {
                // Not being synced with website yet
                // Update the website with details
                foreach ($order->line_items as $line_item) {
                    // Get the product from the website
                    $posProduct = $woocommercePos->get('products', ['sku' => $line_item->sku]);

                    if (!empty($posProduct)) {
                        $parent_id = $posProduct[0]->parent_id;
                        $wooprod_id = $posProduct[0]->id;
                        $stock_quantity = $posProduct[0]->stock_quantity;
                        $update_quantity = ($stock_quantity - $line_item->quantity) >= 0 ? ($stock_quantity - $line_item->quantity) : 0;

                        $update_data = [
                            'manage_stock' => true,
                            'stock_quantity' => $update_quantity,
                            'in_stock' => $update_quantity > 0 ? true : false,
                        ];

                        if ($parent_id > 0) {

                            $status = $woocommercePos->put('products/' . $parent_id . '/variations/' . $wooprod_id,
                                $update_data);
                            $log->debug('Updated the website for the product - ' . print_r($line_item->sku, true));
                        } else {

                            $status = $woocommercePos->put('products/' . $wooprod_id, $update_data);
                            $log->debug('Updated the POS for the product - ' . print_r($line_item->sku, true) . ' on ' . date('Y-m-d H:i:s'));
                        }
                    }
                    if ($status) {
                        // Insert this record to processing sync table
                        $wpdb->insert('es_sync_website_to_pos', [
                            'order_id' => $order->id,
                            'product_sku' => $line_item->sku,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);

                        // If the status is on-hold add it to onhold table
                        if ($order->status == 'on-hold') {
                            $wpdb->insert('es_on_hold_order_sync', [
                                'order_id' => $order->id,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                }
            }
        }
    }
} catch (Exception $ex) {
    $log->warning('Caught exception: ' . $ex->getMessage());
}
