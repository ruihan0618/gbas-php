<?php

namespace GBasJPay;

class AppBase extends ApiResource
{
    /**
     * @return string
     * @throws Error\InvalidRequest
     */
    public static function appBaseUrl()
    {
        if (GBasJPay::$clientId === null) {
            throw new Error\InvalidRequest(
                'Please set a global app ID by GBasJPay::setAppId(<apiKey>)',
                null
            );
        }
        $appId = Util\Util::utf8(GBasJPay::$clientId);
        return "/v1/apps/${appId}";
    }

    /**
     * @return string
     * @throws Error\InvalidRequest
     */
    public static function classUrl()
    {
        $base = static::appBaseUrl();
        $resourceName = static::className();
        return "${base}/${resourceName}s";
    }
}
