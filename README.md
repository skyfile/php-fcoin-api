# php-fcoin-api
Fcoin api call with php

Calling the API v2 on Fcoin Exchange https://www.fcoin.com in php, base on the document https://developer.fcoin.com/en.html

Fcoin::getServerTime();

Fcoin::getSymbols();

Fcoin::getOrderslist(['symbol' => 'btcusdt', 'states' => 'submitted']);

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


Fcoin::getTickData('btcusdt');

Fcoin::getMarketDepthStatus(['symbol' => 'btcusdt', 'level' => 'L20']);

Fcoin::getMarketTransaction(['symbol' => 'btcusdt', 'before_id' => '25688775000', 'limit' => 20])

