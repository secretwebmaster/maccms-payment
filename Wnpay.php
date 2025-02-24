<?php

/**
 **************************************************
 * 名稱: MACCMS万用支付接口模板
 * 版本: 3.0.0
 * 作者: 文尼先生
 * 站長資源: https://3dayseo.com
 * 文尼模板網: https://wntheme.com
 * 最後更新於: 2025-02-25
 **************************************************
 */

namespace app\common\extend\pay;

use think\Log;

class Wnpay
{
    // config
    public $name = '文尼支付';
    public $gateway = 'wnpay'; // unique gateway id
    public $debug = false;
    public $log = true;

    public $channelIdKey = 'payPassAccountId';

    // value
    public $endpoint;
    public $merchantId;
    public $appId;
    public $appKey;
    public $payType;
    public $productId = '8001';
    public $signCase; // upper|lower|null
    public $decimalMultiply = 1; // 金额小数位处理
    public $isInt = true;
    public $decimal = 2; // 只有在 $isInt = false 时有效

    /**
     * 构造函数，用于初始化支付配置。
     */
    public function __construct()
    {
        $this->name = $this->config('display_name', $this->name);

        $this->endpoint = $this->config('endpoint');
        $this->merchantId = $this->config('merchant_id');
        $this->appId = $this->config('appid');
        $this->appKey = $this->config('appkey');
        $this->payType = $this->config('pay_type');
        $this->signCase = $this->config('sign_case', $this->signCase);

        // $this->debug = $this->config('debug', $this->debug);

        if ($this->debug || $this->log) {
            Log::init([
                'type' => 'File',
                'single' => false,
                'path' => LOG_PATH . '/',
                'level' => ['sql', 'error', 'info'],
            ]);
        }
    }

    /**
     * 发起支付请求
     * 
     * @param $user 用户信息
     * @param $order 订单信息
     * @param $param 支付参数
     */
    public function submit($user, $order, $param)
    {
        // 调试输出
        $this->debug($user, '$user of submit()');
        $this->debug($order, '$order of submit()');
        $this->debug($param, '$param of submit()');

        // 处理订单ID
        if ($this->debug) {
            $order_code = "TEST" . time();
        } else {
            $order_code =  $order['order_code'];
        }

        // 金额处理
        if ($this->isInt) {
            // 处理金额 (单位为分 * 100)
            $amount = (int)($order['order_price'] * $this->decimalMultiply);
        } else {
            $amount = number_format($order['order_price'] * $this->decimalMultiply, $this->decimal, '.', '');
        }

        // 构建订单数据
        $data = [
            'mchOrderNo' => $order_code,
            'amount' => $amount,

            'mchId' => $this->merchantId,
            'notifyUrl' => $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/payment/notify/pay_type/' . $this->gateway,
            'returnUrl' =>  $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . mac_url('user/index'),
            'productId' => $this->productId,
            'appId' => $this->appId,

            // 'currency' => 'cny',
            // 'clientIp' => '127.0.0.1',
            // 'device' => 'ios10.3.1',
            // 'subject' => '测试商品1',
            // 'description' => '测试商品描述',
            // 'reqTime' => date('YmdHis'),
            // 'version' => '1.0',
            // 'param1' => '',
            // 'param2' => '',
        ];

        // ASCII 排序
        ksort($data);
        $this->debug($data, 'Sorted unsigned data');

        // 生成签名
        $sign = $this->sign($data);

        $data['sign'] = $sign;

        $this->debug($data, 'POST Form body');

        // 发送POST请求
        $this->debug($this->endpoint, 'POST endpoint');
        $res = mac_curl_post($this->endpoint, $data);
        $res = json_decode($res, true);
        $this->debug($res, 'Result from endpoint');

        // Debug 模式终止跳转
        if ($this->debug) {
            die;
        }

        //跳转到支付页面
        if ($res['code'] == 200 && isset($res['data']['payUrl'])) {
            mac_redirect($res['data']['payUrl']);
        } 

        // 失敗時處理
        elseif($res['code'] == 400 && $res['message'] == '商户单号重复'){
            echo "支付链接爲一次性，如不小心關閉頁面，请重新下单";
        }

        else {
            echo "Payment request failed: " . $res['message'];
        }
    }

    /**
     * 支付回调通知处理
     */
    public function notify()
    {
        // 获取 JSON 请求数据
        // $rawData = file_get_contents('php://input');
        // $this->debug($rawData, 'Received raw JSON data in notify');

        // 解析 JSON 数据
        // $data = json_decode($rawData, true);
        // if (!$data) {
        //     echo 'Fail. Invalid JSON';
        //     return;
        // }

        // Post body
        $data = $_POST;
        $this->debug($data, 'notify() POST data');

        // 签名校验
        $received_sign = $data['sign'] ?? '';
        unset($data['sign']);
        $sign = $this->sign($data);

        $paidStatus = in_array($data['status'], [2, 3]);
        $this->debug($paidStatus, 'paidStatus');

        // 校验签名
        if (!empty($received_sign) && $received_sign === $sign && $paidStatus) {
            $order_id = $data[$this->orderNoKey];
            $this->debug($order_id, '$order_id');
            $res = model('Order')->notify($order_id, $this->gateway);
            $this->debug($res, '$res');

            if ($res['code'] > 1) {
                echo 'Fail. No order is updated';
            } else {
                echo 'success'; // 必须返回 'success'
            }
        } else {
            $this->debug($received_sign, 'received_sign');
            $this->debug($sign, 'calculated sign');
            $this->debug($sign, 'Check sign failed');

            if ($received_sign !== $sign) {
                echo 'Fail. Wrong sign';
            } elseif (!$paidStatus) {
                echo 'Fail. Status not paid';
            } else {
                echo "Other other. Please check.";
            }
        }
    }

    /**
     * 生成簽名
     * 
     * @param array $data 订单数据
     * @return string 生成的签名
     */
    public function sign($data)
    {
        // 删除 sign 參數，確保它不參與簽名計算
        unset($data['sign']);

        // 過濾掉 `null` 值，但保留 `0`（數字零）
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });

        // 按照鍵名 ASCII 升序排序
        ksort($data);
        $unsiged_query_string = http_build_query($data);

        // 拼接 key
        $this->debug($this->appKey, 'key');
        $data['key'] = $this->appKey;
        $unsiged_query_string .= "&key={$this->appKey}";
        $this->debug($unsiged_query_string, '待签名值');

        // MD5 簽名
        $sign = md5($unsiged_query_string);
        if($this->signCase == 'upper'){
            $sign = strtoupper($sign);
        }elseif($this->signCase == 'lower'){
            $sign = strtolower($sign);
        }
        $this->debug($sign, '签名结果');
        
        return $sign;
    }

    /**
     * 获取配置
     * 
     * @param $key 配置键
     * @param $fallback 默认值
     * @return mixed 配置值
     */
    public function config($key, $fallback = null)
    {
        return trim($GLOBALS['config']['pay'][$this->gateway][$key]) ?: $fallback;
    }

    /**
     * 调试信息输出
     * 
     * @param $data 调试数据
     * @param string $title 调试标题
     * @param string $print_mode 打印模式
     */
    public function debug($data, $title = '', $print_mode = 'print_r')
    {
        // 记录日志
        if ($this->log || $this->debug) {
            Log::info($title);
            Log::info($data);
        }

        // 调试模式下输出调试信息
        if ($this->debug) {
            echo "<pre>";
            echo "{$title}:<br>";
            if (class_exists('VarDumper')) {
                VarDumper::dump($data);
            } else {
                if ($print_mode == 'print_r') {
                    print_r($data);
                } else {
                    var_dump($data);
                }
            }
            echo "<br><br>";
            echo "</pre>";
        }
    }
}
