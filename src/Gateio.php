<?php
namespace Gateio;

use GuzzleHttp\Client;

/**
 * Gate.io
 * PHP implement for Gateio v2 API (https://gate.io/api2)
 */
class API
{
    const API_URI = 'https://data.gateio.io/api2/1/';

    private $api_key = '';

    private $api_secret = '';

    private $client;

    private $error = [
        'code' => 0,
        'msg'  => '',
    ];

    public static $obj;

    protected function __construct($key = null, $secret = null)
    {
        $key && ($this->api_key = $key);
        $secret && ($this->api_secret = $secret);
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

    private function getSignature($data = [])
    {
        $body = empty($data) ? '' : http_build_query($data);
        return hash_hmac('sha512', urldecode($body), $this->api_secret);
    }

    /**
     * 设置头部信息
     *
     * @param string $signature  签名字符
     * @return array
     */
    private function getHeaders($signature)
    {
        return [
            'KEY'          => $this->api_key,
            'SIGN'         => $signature,
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ];
    }

    /**
     * 发送请求
     *
     * @param string $method 请求方式 ['POST', 'GET']
     * @param string $url 请求路径
     * @param array $data 请求参数
     */
    private function request($method, $url, $data = [])
    {
        $method = strtoupper($method);
        $params = $method == 'POST' ? [
            'headers'     => $this->getHeaders($this->getSignature($data)),
            'form_params' => $data,
        ] : ['query' => $data];
        try {
            $response = $this->client->request($method, $url, $params);
            $res      = json_decode($response->getBody()->getContents(), true);
            if (isset($res['code']) && $res['code'] != 0) {
                $this->setErr($res['code'], Error::CODE[$res['code']] ?? ($res['message'] ?? ''));
                $res = false;
            }
        } catch (\Exception $e) {
            $this->setErr($e->getCode(), $e->getMessage());
            $res = false;
        }
        return $res;
    }

    /**
     * 设置错误信息
     * @param integer $code
     * @param string $msg
     * @return void
     */
    private function setErr($code = 0, $msg = '')
    {
        $this->error = [
            'code' => $code,
            'msg'  => $msg,
        ];
        return false;
    }

    /**
     * 获取错误信息
     * @param boolean $onlyCode
     * @return void
     */
    public function getErr($onlyCode = false)
    {
        return $onlyCode ? $this->error['code'] : $this->error;
    }

    /**
     * 获取所有支持的交易对
     * GET https://data.gateio.io/api2/1/pairs
     */
    public function getAllPairs()
    {
        return $this->client->request('GET', 'pairs');
    }

    /**
     * Get makretInfo
     * 获取所有系统支持的交易市场的参数信息，包括交易费，最小下单量，价格精度等
     * GET https://data.gateio.io/api2/1/marketinfo
     */
    public function getMarketInfo()
    {
        return $this->client->request('GET', 'marketinfo');
    }

    /**
     * Get marketList
     * 获取所有系统支持的交易市场的详细行情和币种信息，
     * 包括币种名，市值，供应量，最新价格，涨跌趋势，价格曲线等
     * GET https://data.gateio.io/api2/1/marketlist
     * @return json
     * {
     *  "result": "true",
     *  "data": [
     *      {
     *          symbol : 币种标识
     *          name: 币种名称
     *          name_en: 英文名称
     *          name_cn: 中文名称
     *          pair: 交易对
     *          rate: 当前价格
     *          vol_a: 被兑换货币交易量
     *          vol_b: 兑换货币交易量
     *          curr_a: 被兑换货币
     *          curr_b: 兑换货币
     *          curr_suffix: 货币类型后缀
     *          rate_percent: 涨跌百分百
     *          trend: 24小时趋势 up涨 down跌
     *          supply: 币种供应量
     *          marketcap: 总市值
     *          plot: 趋势数据
     *      },
     *      ......
     * ]
     * }
     */
    public function getMarketList()
    {
        return $this->client->request('GET', 'marketlist');
    }

    /**
     * Get Tickers
     * 获取系统支持的所有交易对的 最新，最高，最低 交易行情和交易量，每10秒钟更新
     * GET https://data.gateio.io/api2/1/tickers/[CURR_A]_[CURR_B]
     * @param string $tradPairs  交易对
     * @return json
     * {
     *   "eth_btc": {
     *      baseVolume: 交易量
     *      high24hr:24小时最高价
     *      highestBid:买方最高价
     *      last:最新成交价
     *      low24hr:24小时最低价
     *      lowestAsk:卖方最低价
     *      percentChange:涨跌百分比
     *      quoteVolume: 兑换货币交易量
     *   },
     *  "xrp_btc": {....},
     *   .....
     * }
     */
    public function getTickers($tradPairs = null)
    {
        $tradPairs = $tradPairs == null ? '' : ('/' . strtolower($tradPairs));
        return $this->request('GET', 'tickers' . $tradPairs);
    }

    /**
     * Get orderBooks
     * 获取系统支持的所有交易对的市场深度（委托挂单）
     * 其中 asks 是委卖单, bids 是委买单
     * https: //data.gateio.io/api2/1/orderBook/[CURR_A]_[CURR_B]
     * @param string $tradPairs  交易对
     * @return json
     * {
     *   "btc_usdt": {
     *       "result": "true",
     *       "asks": [
     *          [价格，数量],
     *          ......
     *       ],
     *       "bids": [
     *          [价格，数量],
     *          ......
     *       ],
     *      "elapsed": "1ms"
     *   },
     *   ......
     * }
     */
    public function getOrderBooks($tradPairs = null)
    {
        $tradPairs = $tradPairs == null ? '' : ('/' . strtolower($tradPairs));
        return $this->request('GET', 'orderBook' . $tradPairs);
    }

    /**
     * Get Trade History
     * 获取指定交易对最新80条历史成交记录
     * GET https://data.gateio.io/api2/1/tradeHistory/[CURR_A]_[CURR_B]/[TID]
     * @param string $tradPairs 交易对
     * @param string $tradeId    交易ID，可为空
     * @return json
     * {
     *   "result": "true"
     *   "data": [
     *      {
     *          amount: 成交币种数量
     *          date: 订单时间
     *          rate: 币种单价
     *          total: 订单总额
     *          tradeID: tradeID
     *          type: 买卖类型, buy买 sell卖
     *      },
     *      ......
     *   ],
     *   "elapsed": "6.9.01ms"
     * }
     */
    public function getTradeHistory($tradPairs, $tradeId = null)
    {
        $tradeId = $tradeId == null ? '' : ('/' . $tradeId);
        return $this->request('GET', 'tradeHistory/' . strtolower($tradPairs) . $tradPairs);
    }

    /**
     * Get Candles Ticks
     * 获取交易市场中指定交易对在最近时间内的K线数据
     * GET https://data.gateio.io/api2/1/candlestick2/[CURR_A]_[CURR_B]?group_sec=[GROUP_SEC]&range_hour=[RANGE_HOUR]
     * @param string $tradPairs  交易对
     * @param integer $groupSec  分数数据深度，默认60S
     * @param integer $rangeHour 时间范围
     * @return json
     * {
     *    "result": "true",
     *    "data": [
     *      [
     *          time: 时间戳
     *          volume: 交易量
     *          close: 收盘价
     *          high: 最高价
     *          low: 最低价
     *          open: 开盘价
     *      ],
     *      ......
     *    ]
     * }
     */
    public function getCandlestick2($tradPairs, $groupSec = 60, $rangeHour = 1)
    {
        $groupSec = $groupSec > 60 ? 60 : $groupSec;
        return $this->request('GET', 'candlestick2/' . strtolower($tradPairs), [
            'group_sec'  => abs($groupSec),
            'range_hour' => abs($rangeHour),
        ]);
    }

    /**
     * Get Balance
     * 获取账号资金余额
     * POST https://api.gateio.io/api2/1/private/balances
     * @return json
     * {
     *    "result": "true",
     *    "available": {      可用各类币种资金金额
     *        "BTC": "1000",
     *        "ETH": "968.8",
     *        "ETC": "0",
     *        },
     *    "locked": {         冻结币种金额
     *        "ETH": "1"
     *    }
     * }
     */
    public function getBalances()
    {
        return $this->request('POST', 'private/balances');
    }

    /**
     * Get Deposit Address
     * 获取指定代币充值地址
     * @param string $currency  代币名称
     * @return json
     * {
     *      "result": "true",
     *      "addr": "钱包地址",
     *      "message": "Sucess",
     *      "code": 0
     * }
     */
    public function getDepositAddress($currency)
    {
        return $this->request('POST', 'private/depositAddress', [
            'currency' => strtoupper($currency),
        ]);
    }

    /**
     * Get Deposits Withdrawals
     * 获取充值和提现历史记录
     * POST https://api.gateio.io/api2/1/private/depositsWithdrawals
     * @param integer $start  开始时间戳
     * @param integer $end    结束时间戳
     * @return json
     * {
     *      "result": "true",
     *      "deposits": [
     *         {
     *          "id": "c204730",
     *          "currency": "币种",
     *          "address": "充值地址",
     *          "amount": "金额",
     *          "txid": "210496",
     *          "timestamp": "1474962729",
     *          "status": "DONE"  记录状态 DONE:完成; CANCEL:取消; REQUEST:请求中
     *         },
     *        ......
     *      ],
     *      "withdraws": [
     *         {
     *          "currency": "币种",
     *          "address": "提现地址",
     *          "amount": "金额",
     *          "txid": "210496",
     *          "timestamp": "1474962729",
     *          "status": "DONE"  记录状态 DONE:完成; CANCEL:取消; REQUEST:请求中
     *         },
     *        ......
     *      ]
     *      "message": "Success"
     * }
     */
    public function getDepositsWithdrawals($start, $end = null)
    {
        return $this->request('POST', 'private/depositsWithdrawals', [
            'start' => $start,
            'end'   => $end == null ? time() : $end,
        ]);
    }

    /**
     * Create Order
     * 创建买入订单
     * POST https://api.gateio.io/api2/1/private/buy
     * @param string $tradPair  交易对  eg:ltc_btc
     * @param string $rate      下单价格
     * @param string $amount    数量
     * @param string $orderType 订单类型可选[‘’, 'ioc'] 默认为'',ioc为立刻执行否则取消订单
     * @return json
     * {
     *      "result":"true",
     *      "message":"Success",
     *      "orderNumber":"订单号",
     *      "rate":"下单价格",
     *      "leftAmount":"剩余数量",
     *      "filledAmount":"成交数量",
     *      "filledRate":"成家价格"
     * }
     */
    public function buy($tradPair, $rate, $amount, $orderType = '')
    {
        return $this->request('POST', 'private/buy', [
            'currencyPair' => strtolower($tradPair),
            'rate'         => $rate,
            'amount'       => $amount,
            'orderType'    => $orderType !== 'ioc' ? '' : 'ioc',
        ]);
    }

    /**
     * Create Order
     * 创建买入订单
     * POST https://api.gateio.io/api2/1/private/sell
     * @param string $tradPair  交易对  eg:ltc_btc
     * @param string $rate      下单价格
     * @param string $amount    数量
     * @param string $orderType 订单类型可选[‘’, 'ioc'] 默认为'',ioc为立刻执行否则取消订单
     * @return json
     * {
     *      "result":"true",
     *      "message":"Success",
     *      "orderNumber":"订单号",
     *      "rate":"下单价格",
     *      "leftAmount":"剩余数量",
     *      "filledAmount":"成交数量",
     *      "filledRate":"成家价格"
     * }
     */
    public function sell($tradPair, $rate, $amount, $orderType = '')
    {
        return $this->request('POST', 'private/sell', [
            'currencyPair' => strtolower($tradPair),
            'rate'         => $rate,
            'amount'       => $amount,
            'orderType'    => $orderType !== 'ioc' ? '' : 'ioc',
        ]);
    }

    /**
     * Cancel Order
     * 取消订单
     * POST https://api.gateio.io/api2/1/private/cancelOrder
     * @param string $orderNumber   订单单号
     * @param string $currencyPair  交易对 eg: ltc_btc
     * @return boolean
     */
    public function cancelOrder($orderNumber, $currencyPair)
    {
        return $this->request('POST', 'private/cancelOrder', [
            'orderNumber'  => $orderNumber,
            'currencyPair' => strtolower($tradPair),
        ]);
    }

    /**
     * Cancel Orders
     * 批量取消订单
     * POST https: //api.gateio.io/api2/1/private/cancelOrders
     * @param array $orderList  订单列表 eg: [$orderNumber => $currencyPair,.....]
     * @return boolean
     */
    public function cancelOrders($orderList)
    {
        if (!is_array($orderList)) {
            return false;
        }
        $orders = [];
        foreach ($orderList as $orderNumber => $currencyPair) {
            $orders[] = compact('orderNumber', 'currencyPair');
        }
        return $this->client->request('POST', 'private/cancelOrders', [
            'orders_json' => json_encode($orders),
        ]) ? true : false;
    }

    /**
     * Cancel All Orders
     * 取消所有指定类型订单
     * POST https://api.gateio.io/api2/1/private/cancelAllOrders
     * @param string $currencyPair  交易对 eg: ltc_btc
     * @param integer $type     下单类型 [0:卖出,1:买入,-1:不限制]
     * @return boolean
     */
    public function cancelAllOrders($currencyPair, $type = -1)
    {
        if (!in_array($type, [0, 1, -1])) {
            return false;
        }
        return $this->client->request('POST', 'private/cancelAllOrders', [
            'type'         => $type,
            'currencyPair' => strtolower($currencyPair),
        ]) ? true : false;
    }

    /**
     * Get Order Status
     * 获取订单状态
     * POST https://api.gateio.io/api2/1/private/getOrder
     * @param string $orderNumber        订单单号
     * @param string $currencyPair      交易对 eg: eth_btc
     * @return json
     * {
     *   "result":"true",
     *   "order":{
     *       "id":"15088",
     *       "status":"cancelled",  订单状态 open已挂单 cancelled已取消 closed已完成
     *       "currencyPair":"eth_btc",   交易对
     *       "type":"sell",         买卖类型 sell卖出, buy买入
     *       "rate":811,            价格
     *       "amount":"0.39901357", 买卖数量
     *       "initialRate":811,     下单价格
     *       "initialAmount":"1"    下单数量
     *       },
     *   "message":"Success"
     * }
     */
    public function getOrder($orderNumber, $currencyPair)
    {
        return $this->request('POST', 'private/getOrder', [
            'orderNumber'  => $orderNumber,
            'currencyPair' => strtolower($currencyPair),
        ]);
    }

    /**
     * Get Open Orders
     * 获取当前挂出订单列表
     * POST https://api.gateio.io/api2/1/private/openOrders
     * @param string $currencyPair  交易对
     * @return json
     * {
     *   "result": "true",
     *   "message": "Success",
     *   "code": 0,
     *   "elapsed": "6.262ms",
     *   "orders": [
     *           {
     *           "orderNumber": "30032151",
     *           "type": "buy",             买卖类型 buy:买入;sell:卖出
     *           "rate": 21367.521367521,   交易单价
     *           "amount": "0.0936",        订单总数量 剩余未成交数量
     *           "total": "2000",           总计
     *           "initialRate": 21367.521367521,    下单价格
     *           "initialAmount": "0.0936",     下单数量
     *           "filledRate": 0,               成交价格
     *           "filledAmount": 0,             成交数量
     *           "currencyPair": "eth_btc",     订单交易对
     *           "timestamp": "1407828913",     时间戳
     *           "status": "open"               订单状态
     *           }
     *       ]
     *   }
     */
    public function openOrders($currencyPair)
    {
        return $this->request('POST', 'private/openOrders', [
            'orderNumber'  => $orderNumber,
            'currencyPair' => strtolower($currencyPair),
        ]);
    }

    /**
     * Get Trade History
     * 获取 24小时内成交记录列表
     * POST https://api.gateio.io/api2/1/private/tradeHistory
     * @param string $currencyPair  交易对
     * @param string $orderNumber   订单号 可为空
     * @return json
     * {
     *   "result": "true",
     *   "message": "Success",
     *   "trades": [
     *           {
     *           "id": "7942422",
     *           "orderid": "38100491",     订单ID
     *           "pair": "ltc_btc",         交易对
     *           "type": "buy",             买卖类型
     *           "rate": "0.01719",         买卖价格
     *           "amount": "0.0588",        订单买卖数量
     *           "time": "06-12 02:49:11",  订单时间
     *           "time_unix": "1402512551"  时间戳
     *           },
     *          ....
     *       ]
     *   }
     */
    public function tradeHistory($currencyPair, $orderNumber = '')
    {
        return $this->request('POST', 'private/tradeHistory', [
            'currencyPair' => strtolower($currencyPair),
            'orderNumber'  => $orderNumber,
        ]);
    }

    /**
     * Withdraw
     * 提现
     * POST https: //api.gateio.io/api2/1/private/withdraw
     * @param string $currency  提现币种 eg: btc
     * @param string $amount    提现数量
     * @param string $address   提现地址
     * @return boolean
     */
    public function withdraw($currency, $amount, $address)
    {
        return $this->request('POST', 'private/withdraw', [
            'currency' => strtolower($currency),
            'amount'   => $amount,
            'address'  => trim($address),
        ]) ? true : false;
    }
}
