<?php

//获取方式：登录 [Dashboard](https://jpay.weidun.biz)->商户后台->右上角API开发-> APIKEY
const CLIENT_ID = '0DX1S8';
const APP_KEY = 'BaVj1quQPKuK3QOArhbDnTKIHUgQmOdL';

\GBasJPay\GBasJPay::setDebug(true); //调试模式   true /false
\GBasJPay\GBasJPay::setApiMode('live'); //环境  live 线上，sandbox 沙盒
\GBasJPay\GBasJPay::setClientId(CLIENT_ID);   // 设置 CLIENT ID
\GBasJPay\GBasJPay::setApiKey(APP_KEY);    // 设置 API Key