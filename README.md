# php-gateio-api
Gate.io api call with php

Calling the API v2  base on the document https://gate.io/api2

    $api= Gateio::instance(KEY,SECRET);
    // 获取所有交易对
    $res = $api->getAllPairs();
