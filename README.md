# php-fcoin-api
Fcoin api call with php

Calling the API v2 on Fcoin Exchange https://www.fcoin.com in php, base on the document https://developer.fcoin.com/en.html

    $api= Fcoin::instance(KEY,SECRET);
    // 获取所有交易对
    $res = $api->getCurrencies();
