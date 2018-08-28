<?php
require __DIR__ . '/vendor/autoload.php';

use Fcoin\API as Fcoin;

echo 'getServerTime() ' . Fcoin::getServerTime() . PHP_EOL;

echo 'getSCurrencies() ' . Fcoin::getCurrencies() . PHP_EOL;

echo 'getSymbols() ' . Fcoin::getSymbols() . PHP_EOL;

echo 'getBalance() ' . Fcoin::getBalance() . PHP_EOL;

echo 'getOrderslist ' . Fcoin::getOrderslist(['symbol' => 'btcusdt', 'states' => 'submitted']) . PHP_EOL;

$order = Fcoin::createOrders(
    [
        'type'   => 'limit',
        'side'   => 'buy',
        'amount' => '1.0',
        'price'  => '1.0',
        'symbol' => 'btcusdt',
    ]
);

$order_json = json_decode($order, true);
$order_id   = $order_json['data'];

echo 'createOrders ' . $order_id . PHP_EOL;

echo 'getOrder ' . Fcoin::getOrder(['order_id' => $order_id]) . PHP_EOL;

echo 'getOrderTransaction ' . Fcoin::getOrderTransaction(
    [
        'order_id' => $order_id,
    ]
) . PHP_EOL;

echo 'cancelOrder ' . Fcoin::cancelOrder(
    [
        'order_id' => $order_id,
    ]
) . PHP_EOL;

echo 'getTickData() ' . Fcoin::getTickData(['symbol' => 'btcusdt']) . PHP_EOL;

echo 'getMarketDepthStatus() ' . Fcoin::getMarketDepthStatus(['symbol' => 'btcusdt', 'level' => 'L20']) . PHP_EOL;

echo 'getMarketTransaction() ' . Fcoin::getMarketTransaction(['symbol' => 'btcusdt', 'before_id' => '25688775000', 'limit' => 20]) . PHP_EOL;
