<?php

require dirname(__FILE__) . '/../../init.php';
// 示例配置文件，测试请根据文件注释修改其配置
require dirname(__FILE__) . '/../config.php';

// 查询 charge 对象
try {
    $refund = \GBasJPay\Refund::retrieve('ch_47a09cb32de41b1d3c250ab2',[
        'refund_id' => 're_2841c7fbf2d8e318c730386e'
    ]);
    echo $refund."\r\n";
} catch (\GBasJPay\Error\Base $e) {
    if ($e->getHttpStatus() != null) {
        header('Status: ' . $e->getHttpStatus());
        echo $e->getHttpBody();
    } else {
        echo $e->getMessage();
    }
}
