<?php

//获取方式：登录 [Dashboard](https://jpay.vmart.vip)->商户后台->右上角API开发-> APIKEY
const CLIENT_ID = '10100';
const APP_KEY = 'du7o4l89k04ml8mwlsjts6dr01sywykx';

\GBasJPay\GBasJPay::setDebug(true); //调试模式   true /false
\GBasJPay\GBasJPay::setApiMode('live'); //环境  live 线上，sandbox 沙盒
\GBasJPay\GBasJPay::setClientId(CLIENT_ID);   // 设置 CLIENT ID
\GBasJPay\GBasJPay::setApiKey(APP_KEY);    // 设置 API Key