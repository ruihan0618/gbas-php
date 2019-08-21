<?php

require dirname(__FILE__) . '/../../init.php';
// 示例配置文件，测试请根据文件注释修改其配置
require dirname(__FILE__) . '/../config.php';


// 此处为 Content-Type 是 application/json 时获取 POST 参数的示例
$input_data = json_decode(file_get_contents('php://input'), true);

$channel = 'Wechat';  $orderNo = substr(md5(time()), 0, 18);

try {
    $ch = \GBasJPay\Charge::create([
        'channel'   => $channel,                // 支付使用的第三方支付渠道取值
        'out_order_no' => $orderNo,  //外部订单号 ，为空时由系统生成
        'price'    => 100,   // 订单总金额
        'currency' => 'HKD',
        'description' => 'test', //订单备注说明
        'operator'=>'123456',
        'notify'=> 'https://www.vmart.vip/v1/notify',   //异步通知地址
     ]);
    echo $ch."\r\n";                                       // 输出 返回的支付凭据 Charge
} catch (\GBasJPay\Error\Base $e) {
    // 捕获报错信息
    if ($e->getHttpStatus() != null) {
        echo $e->getHttpBody();
    } else {
        echo $e->getMessage();
    }
}
