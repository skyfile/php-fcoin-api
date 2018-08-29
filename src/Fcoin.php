<?php
namespace Fcoin;

use GuzzleHttp\Client;

/**
 * Fcoin
 * PHP implement for Fcoin v2 API (https://developer.fcoin.com/ZH.html)
 */
class API
{
    const API_URI = 'https://api.fcoin.com/v2/';

    private $api_key = '';

    private $api_secret = '';

    private $cert_pem = '';

    private $time_out = 2;

    private $error = [
        'code' => 0,
        'msg'  => '',
    ];

    private $client;

    // 订单类型
    const ORDER_STATUS = [
        'submitted',        // 已提交
        'partial_filled',   // 部分成交
        'partial_canceled', // 部分成交已撤销
        'filled',           // 完全成交
        'canceled',         // 已撤销
        'pending_cancel',   // 撤销已提交
    ];

    // 行情深度类型
    const DEPTH_LEVEL = [
        'L20',  // 20档行情深度
        'L100', // 100档行情深度
        'full', // 全量行情深度
    ];

    // 蜡烛图的种类
    const RESOLUTIONS = [
        'M1',  // 1分钟
        'M3',  // 3分钟
        'M5',  // 5分钟
        'M15', // 15分钟
        'M30', // 30分钟
        'H1',  // 1小时
        'H4',  // 4小时
        'H6',  // 6小时
        'D1',  // 1日
        'W1',  // 1周
        'MN',  // 1月
    ];

    public static $obj;

    protected function __construct($key = null, $secret = null, $certPemPath = null, $timeOut = null)
    {
        $key && ($this->api_key = $key);
        $secret && ($this->api_secret = $secret);
        $timeOut && ($this->time_out = $timeOut);
        $certPemPath && ($this->cert_pem = $certPemPath);
        $this->client = new Client(['base_uri' => self::API_URI]);
    }

    public static function instance()
    {
        $args = func_get_args();
        $k    = md5(implode(':', func_get_args()));
        if (!isset(self::$obj[$k])) {
            self::$obj[$k] = new self(...$args);
        }
        return self::$obj[$k];
    }

    private function getSignature($method, $url, $time, $data = [])
    {
        $uri       = self::API_URI . $url;
        $body      = empty($data) ? '' : http_build_query($data);
        $signature = $method . $uri;
        if ($method == 'GET') {
            $signature .= ($body != '' ? '?' . $body : '') . $time;
        } else {
            $signature .= $time . $body;
        }
        $signature = hash_hmac('sha1', base64_encode($signature), $this->api_secret, true);
        return base64_encode($signature);
    }

    private static function genQueryString($data)
    {
        if (empty($data)) {
            return '';
        }
        ksort($data);
        return '?' . http_build_query($data);
    }

    private static function getHeaders($key, $signature, $time, $needPost = true)
    {
        return array_merge([
            'FC-ACCESS-KEY'       => $key,
            'FC-ACCESS-SIGNATURE' => $signature,
            'FC-ACCESS-TIMESTAMP' => $time,
        ], $needPost ? [
            'Content-Type' => 'application/json;charset=UTF-8',
        ] : []);
    }

    public function getCert()
    {
        if (!$this->cert_pem || !is_file($this->cert_pem)) {
            try {
                $response = (new Client())->get('https://curl.haxx.se/ca/cacert.pem');
                $file     = $response->getBody()->getContents();
            } catch (\Exception $e) {
                $file = '';
            }
            if (!$file) {
                return [];
            }
            $filePath = getcwd() . '/cacert.pem';
            if (file_put_contents($filePath, $file)) {
                $this->cert_pem = $filePath;
            }
        }
        return ['verify' => [$this->cert_pem]];
    }

    public function request($method, $url, $data = [], $needSign = false)
    {
        $method = strtoupper($method);
        $params = $this->getCert();
        if ($needSign) {
            $localTime         = round(microtime(true) * 1000);
            $signature         = $this->getSignature($method, $url, $localTime, $data);
            $params['headers'] = self::getHeaders($this->api_key, $signature, $localTime, $method == 'POST');
        }
        $params = array_merge($params, $method == 'POST' ? [
            'json' => $data,
        ] : [
            'query' => $data,
        ], ['timeout' => $this->time_out]);
        try {
            $response = $this->client->request($method, $url, $params);
            $res      = json_decode($response->getBody()->getContents(), true);
            if (!isset($res['status']) || $res['status'] != 0) {
                $res = $this->setErr($res['status'], Error::CODE[$res['status']] ?? ($res['msg'] ?? ''));
            }
        } catch (\Exception $e) {
            $code = $e->getCode();
            $msg  = Error::CODE[$code] ?? $e->getMessage();
            $this->setErr($code, $msg);
            $res = false;
        }
        return $res;
    }

    private function setErr($code = 0, $msg = '')
    {
        $this->error = [
            'code' => $code,
            'msg'  => $msg,
        ];
        return false;
    }

    public function getErr($onlyCode = false)
    {
        return $onlyCode ? $this->error['code'] : $this->error;
    }

    /**
     *   Get server time
     *   GET https://api.fcoin.com/v2/public/server-time
     */
    public function getServerTime()
    {
        return $this->request('GET', 'public/server-time');
    }

    /**
     *   Get support symbols
     *   GET https://api.fcoin.com/v2/public/symbols
     */
    public function getSymbols()
    {
        return $this->request('GET', 'public/symbols');
    }

    /**
     *   Get support currencties
     *   GET https://api.fcoin.com/v2/public/currencies
     */
    public function getCurrencies()
    {
        return $this->request('GET', 'public/currencies');
    }

    /**
     *   Get Account balance
     *   GET https://api.fcoin.com/v2/accounts/balance
     */
    public function getBalance()
    {
        return $this->request('GET', 'accounts/balance', [], true);
    }

    /**
     *   Create order
     *   创建订单
     *   POST https://api.fcoin.com/v2/orders
     *  @param string $symbol   交易对
     *  @param string $side     交易方向 ['buy'购买， 'sell'卖出]
     *  @param string $price    价格
     *  @param integer $amount  下单量
     *  @param string $type     订单类型 ['limit'限价，'market'市价]
     *  @return json result
     *    {
     *    "status":0,
     *    "data":"9d17a03b852e48c0b3920c7412867623"
     *    }
     */
    public function createOrders($symbol, $side, $price, $amount, $type = 'limit')
    {
        if (!in_array($side, ['buy', 'sell'])) {
            return false;
        }
        if (!in_array($type, ['limit', 'market'])) {
            return false;
        }
        $order = [
            'symbol' => $symbol,
            'side'   => $side,
            'type'   => $type,
            'price'  => $price,
            'amount' => $amount,
        ];
        ksort($order);
        return $this->request('POST', 'orders', $order, true);
    }

    /**
     *  Get order list
     *  GET https://api.fcoin.com/v2/orders
     *  @param string $symbol   交易对
     *  @param string $status   订单状态
     *  @param integer $limit   每页的订单数量，默认为20条
     *  @param string $before   查询某个页码之前的订单
     *  @param string $after    查询某个页码之后的订单
     *  @return json result
     *    {
     *        "status":0,
     *        "data":[
     *           {
     *               "id":"string",
     *               "symbol":"string",
     *               "type":"limit",
     *               "side":"buy",
     *               "price":"string",
     *               "amount":"string",
     *               "state":"submitted",
     *               "executed_value":"string",
     *               "fill_fees":"string",
     *               "filled_amount":"string",
     *               "created_at":0,
     *               "source":"web"
     *           }
     *       ]
     *   }
     */
    public function getOrderslist($symbol, $states, $limit = 20, $before = null, $after = null)
    {
        if (!in_array($states, self::ORDER_STATUS)) {
            return $this->setErr(400, Error::CODE[400]);
        }
        $criteria = [
            'symbol' => $symbol,
            'states' => $states,
            'limit'  => (int) $limit,
        ];
        $before && ($criteria['before'] = $before);
        $after && ($criteria['after'] = $after);
        ksort($criteria);
        return $this->request('GET', 'orders', $criteria, true);
    }

    /**
     *  Get orders
     *  获取指定订单
     *  GET https://api.fcoin.com/v2/orders
     *  @param string $orderId  订单ID
     *
     *   @return json result
     *    {
     *       "status":0,
     *       "data":{
     *            "id":"9d17a03b852e48c0b3920c7412867623",
     *           "symbol":"string",
     *            "type":"limit",
     *            "side":"buy",
     *           "price":"string",
     *            "amount":"string",
     *            "state":"submitted",
     *            "executed_value":"string",
     *           "fill_fees":"string",
     *           "filled_amount":"string",
     *           "created_at":0,
     *           "source":"web"
     *       }
     *    }
     */
    public function getOrder($orderId)
    {
        return $this->request('GET', 'orders/' . $orderId, [], true);
    }

    /**
     *  Cancel order
     *  申请撤销订单
     *  POST https://api.fcoin.com/v2/orders/{order_id}/submit-cancel
     *  @param string $orderId  订单ID
     *  @return boolean
     */
    public function cancelOrder($orderId)
    {
        $path = 'orders/' . $orderId . '/submit-cancel';
        $res  = $this->request('POST', $path, [], true);
        if ($res && $res['status'] == 0) {
            return true;
        }
        return false;
    }

    /**
     *  Get order transaction
     *  查询指定订单的成交记录
     *  GET https://api.fcoin.com/v2/orders/{order_id}/match-results
     *  @param string $orderId  订单ID
     *  @return json result
     *    {
     *       "status": 0,
     *       "data": [
     *        {
     *           "price": "string",
     *            "fill_fees": "string",
     *            "filled_amount": "string",
     *            "side": "buy",
     *            "type": "limit",
     *            "created_at": 0
     *        }
     *        ]
     *    }
     */
    public function getOrderTransaction($orderId)
    {
        $path = 'orders/' . $orderId . '/match-results';
        return $this->request('GET', $path, [], true);
    }

    /**
     *  Get tick data of a symbol
     *  获取 ticker 数据
     *  GET https://api.fcoin.com/v2/market/ticker/$symbol
     *  @param string $symbol   交易对
     *  @return json result
     *    {
     *       "status": 0,
     *       "data": {
     *           "type": "ticker.btcusdt",
     *           "seq": 680035,
     *           "ticker": [
     *               7140.890000000000000000,       // 最新成交价
     *               1.000000000000000000,          // 最近一笔成交的成交量
     *               7131.330000000,                // 最大买一价
     *               233.524600000,                 // 最大买一量
     *               7140.890000000,                // 最小卖一价
     *               225.495049866,                 // 最小卖一量
     *               7140.890000000,                // 24小时前成交价
     *               7140.890000000,                // 24小时内最高价
     *               7140.890000000,                // 24小时内最低价
     *               1.000000000,                   // 24小时内基准货币成交量, 如 btcusdt 中 btc 的量
     *               7140.890000000000000000        // 24小时内计价货币成交量, 如 btcusdt 中 usdt 的量
     *           ]
     *       }
     *    }
     */
    public function getTickData($symbol)
    {
        return $this->request('GET', 'market/ticker/' . strtolower($symbol));
    }

    /**
     *  Get market depth status
     *  获取最新的深度明细
     *  GET https://api.fcoin.com/v2/market/depth/$level/$symbol
     *  @param string $symbol   交易对
     *  @param string $level    深度描述 可选['L20','L100', 'full']
     *   @return json result
     *   {
     *    "type": "depth.L20.ethbtc",
     *    "ts": 1523619211000,
     *    "seq": 120,
     *    // bids 和 asks 对应的数组一定是偶数条目, 买(卖)1价, 买(卖)1量, 依次往后排列
     *    "bids": [0.000100000, 1.000000000, 0.000010000, 1.000000000],
     *    "asks": [1.000000000, 1.000000000]
     *   }
     *
     *   @example getMarketDepthStatus(['symbol' => 'btcustd', 'level' => 'L20'])
     */
    public function getMarketDepthStatus($symbol, $level = 'L20')
    {
        if (!in_array($level, self::DEPTH_LEVEL)) {
            return $this->setErr(400, Error::CODE[400]);
        }
        return $this->request('GET', 'market/depth/' . $level . '/' . strtolower($symbol));
    }

    /**
     *  Get Market Committed Transactions
     *  获取最新的成交明细
     *  GET https://api.fcoin.com/v2/market/trades/$symbol
     *  @param string $symbol   交易对
     *  @param string $before   查询某个ID之前的 tradeId
     *  @param integer $limit   查询数量 默认20条
     *  @return json result
     *    {"id":null,
     *    "ts":1523693400329,
     *    "data":[
     *       {
     *            "amount":1.000000000,
     *            "ts":1523419946174,
     *            "id":76000,
     *            "side":"sell",
     *            "price":4.000000000
     *       },
     *       {
     *            "amount":1.000000000,
     *            "ts":1523419114272,
     *            "id":74000,
     *            "side":"sell",
     *            "price":4.000000000
     *       },
     *       {
     *            "amount":1.000000000,
     *            "ts":1523415182356,
     *            "id":71000,
     *            "side":"sell",
     *            "price":3.000000000
     *       }
     *      ]
     *    }
     */
    public function getMarketTransaction($symbol, $before_id, $limit = 20)
    {
        $params = [
            'before_id' => $before_id,
            'limit'     => (int) $limit,
        ];
        ksort($params);
        return $this->request('GET', 'market/trades/' . $symbol, $params);
    }

    /**
     *  Get Candle Data
     *  获取 Candle 信息
     *  GET https://api.fcoin.com/v2/market/candles/$resolution/$symbol
     *  @param string $symbol   交易对
     *  @param integer $limit   查询数量，默认20条
     *  @param string $resolution   数据粒度 可选 self::RESOLUTION
     *  @param string $before   查询某个ID之前的 Canndle
     *  @return json
     *  {
     *    "type":"candle.M1.ethbtc",
     *    "id":1523691480,
     *    "seq":11400000,
     *    "open":2.000000000,
     *    "close":2.000000000,
     *    "high":2.000000000,
     *    "low":2.000000000,
     *    "count":0,
     *    "base_vol":0,         // 基准货币成交量
     *    "quote_vol":0         // 计价货币成交量
     * }
     */
    public function getCandle($symbol, $limit = 20, $resolution = 'M1', $before = null)
    {
        if (!in_array($resolution, self::RESOLUTIONS)) {
            return $this->setErr(400, Error::CODE[400]);
        }
        $params = [
            'before_id' => $before,
            'limit'     => (int) $limit,
        ];
        ksort($params);
        return $this->request('GET', 'market/candles/' . $resolution . '/' . $symbol, $params);
    }
}
