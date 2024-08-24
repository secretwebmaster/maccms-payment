<?php
/**
**************************************************
* 名稱: MACCMS万用支付接口模板
* 版本: 2.0.0
* 作者: 文尼先生
* 站長資源: https://3dayseo.com
* 文尼模板網: https://wntheme.com
* 最後更新於: 2024-07-16
**************************************************
*/

namespace app\common\extend\pay;
use think\Log;

class Wnpay {

    public $name = '在线支付';
    public $ver = '2.0.0';
    public $debug = false;
    public $log = true;

    public $endpoint;
    public $merchant_id;
    public $appid;
    public $appkey;

    public $pay_type;

    /**
     * 构造函数，用于初始化支付配置。
     */
    public function __construct()
    {
        $this->endpoint = trim($GLOBALS['config']['pay']['wnpay']['endpoint']);
        $this->merchant_id = trim($GLOBALS['config']['pay']['wnpay']['merchant_id']);
        $this->appid = trim($GLOBALS['config']['pay']['wnpay']['appid']);
        $this->appkey = trim($GLOBALS['config']['pay']['wnpay']['appkey']);
        $this->pay_type = trim($GLOBALS['config']['pay']['wnpay']['pay_type']);

        if($this->debug || $this->log){
            Log::init([
                'type' => 'File',
                'single'=> false,
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
        //处理订单ID
        if($this->debug){
            $order_code = "TEST" . time();
        }else{
            $order_code =  $order['order_code'];
        }

        $this->debug($user, 'submit() user');
        $this->debug($order, 'submit() order');
        $this->debug($param, 'submit() param');

        //处理金额 (单位为分 * 100)
        $amount = (int)($order['order_price'] * 100);

        //构建订单数据
        $data = [
            'mchId' => $this->merchant_id,
            'appId' => $this->appid,
            'productId' => '8018',
            'mchOrderNo' => $order_code,
            'amount' => $amount,
            'currency' => 'cny',
            'notifyUrl' => $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . '/index.php/payment/notify/pay_type/wnpay',
            'returnUrl' => $GLOBALS['http_type'] . $_SERVER['HTTP_HOST'] . mac_url('user/upgrade'),
            'subject' => '在线充值',
            'body' => '积分充值（UID：'.$user['user_id'].'）',
            'reqTime' => date('YmdHis'),
            'version' => '1.0',
        ];

        //生成签名数据
        $signed_data = $this->generate_signed_data($data);
        $this->debug($signed_data, 'POST Form body');

        //发送POST请求
        $this->debug($this->endpoint, 'POST endpoint');
        $res = mac_curl_post($this->endpoint, $signed_data);
        $res = json_decode($res, true);
        $this->debug($res, 'Result from endpoint');

        //Debug 模式终止跳转
        if($this->debug){
           die;
        }

        //跳转到支付页面
        $url = $res['payJumpUrl'];
        mac_redirect($url);
    }

    /**
     * 支付回调通知处理
     */
    public function notify()
    {
        $this->debug($_POST, 'Received data in notify');

        $data = $_POST;
        unset($data['sign']);

        $received_sign = $_POST['sign'];
        $sign = $this->sign($data, 'generate_signed_data() in notify');

        //校验签名并处理订单
        if (!empty($received_sign) && $received_sign == $sign) {
            $order_id = $_POST['mchOrderNo'];
            $res = model('Order')->notify($order_id, 'wnpay');
            if ($res['code'] > 1) {
                echo 'Fail. No order is updated';
            } else {
                echo 'success';
            }
        } else {
            $this->debug($sign, 'check sign');
            echo 'Fail. Wrong sign';
        }
    }

    /**
     * 生成带签名的数据
     * 
     * @param $data 原始订单数据
     * @return array 带签名的订单数据
     */
    public function generate_signed_data($data)
    {
        $this->debug($data, 'unsigned_data');

        //生成签名
        $sign = $this->sign($data);
        $data['sign'] = $sign;
        return $data;
    }

    /**
     * 生成签名
     * 
     * @param $data 订单数据
     * @return string 生成的签名
     */
    public function sign($data)
    {
        $unsiged_query_string = "";
        $index = 0;
        $data = array_filter($data);
        unset($data['sign']);
        ksort($data);
        reset($data);
        $this->debug($data, 'array data before sign');

        //遍历数据生成待签名字符串
        foreach ($data as $k => $v) {
            if($index){
                $unsiged_query_string .= "&$k=$v";
            }else{
                $unsiged_query_string .= "$k=$v";
            }
            $index++;
        }

        //拼接密钥生成签名
        $this->debug($this->appkey, 'key');
        $unsiged_query_string .= "&key={$this->appkey}";
        $this->debug($unsiged_query_string, '待签名值');

        $sign = strtoupper(md5($unsiged_query_string));
        $this->debug($sign, '签名结果');
        return $sign;
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
        //记录日志
        if($this->log || $this->debug){
            Log::info($data);
        }

        //调试模式下输出调试信息
        if($this->debug){
            echo "<pre>";
            echo "{$title}:<br>";
            if (class_exists('VarDumper')) {
                VarDumper::dump($data);
            }else{
                if($print_mode == 'print_r'){
                    print_r($data);
                }else{
                    var_dump($data);
                }
            }
            echo "<br><br>";
            echo "</pre>";
        }
    }
}
