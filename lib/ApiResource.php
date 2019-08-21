<?php

namespace GBasJPay;

use GBasJPay\Error\InvalidRequest;

abstract class ApiResource extends GBasJPayObject
{
    private static $HEADERS_TO_PERSIST = ['JPay-Version' => true];

    protected static $signOpts = [
        'uri' => true,
        'time' => true,
    ];

    public static function baseUrl()
    {
        return GBasJPay::$apiLiveBase;
    }

    /**
     * @return $this
     * @throws InvalidRequest
     */
    public function refresh()
    {
        $requestor = new ApiRequestor($this->_opts->apiKey, static::baseUrl(), $this->_opts->signOpts);
        $url = $this->instanceUrl();

        list($response, $this->_opts->apiKey) = $requestor->request(
            'get',
            $url,
            $this->_retrieveOptions,
            $this->_opts->headers
        );
        $this->refreshFrom($response, $this->_opts);
        return $this;
    }

    /**
     * @return string The name of the class, with namespacing and underscores
     *    stripped.
     */
    public static function className()
    {
        $class = get_called_class();
        // Useful for namespaces: Foo\Charge
        if ($postfix = strrchr($class, '\\')) {
            $class = substr($postfix, 1);
        }
        // Useful for underscored 'namespaces': Foo_Charge
        if ($postfixFakeNamespaces = strrchr($class, '')) {
            $class = $postfixFakeNamespaces;
        }
        if (substr($class, 0, strlen('JPay')) == 'JPay') {
            $class = substr($class, strlen('JPay'));
        }
        $class = str_replace('_', '', $class);
        $name = urlencode($class);
        $name = strtolower($name);
        return $name;
    }

    /**
     * @return string The endpoint URL for the given class.
     */
    public static function classUrl()
    {
        return "/api/v1.0/gateway/partners/".GBasJPay::$clientId."/orders/";
    }


    public static function _queryParams(){

        $_arr = array('time'=> self::getMilliSecond(),'nonce_str'=> self::getNonceStr(),'sign'=> self::createSign());

        return http_build_query($_arr);
    }
    /**
     * @return string
     */
    public static function createSign(){
        //签名步骤一：构造签名参数
        $string = GBasJPay::$clientId . '&' . self::getMilliSecond() . '&' . self::getNonceStr() . "&" . GBasJPay::$apiKey;
        echo $string."\r\n";
        //签名步骤三：SHA256加密
        $string = hash('sha256', utf8_encode($string));
        //签名步骤四：所有字符转为小写
        $result = strtolower($string);
        return $result;
    }

    /**
     * @return array|string
     */
    private static function getMilliSecond()
    {
        //获取毫秒的时间戳
        $time = explode(" ", microtime());
        $millisecond = "000".($time[0] * 1000);
        $millisecond2 = explode(".", $millisecond);
        $millisecond = substr($millisecond2[0],-3);
        $time = $time[1] . $millisecond;
        return $time;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function getNonceStr($length = 30)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * @return string
     * @throws InvalidRequest
     */
    public function instanceUrl()
    {
        $id = $this['id'];
        $class = get_called_class();
        if ($id === null) {
            $message = "Could not determine which URL to request: "
                . "$class instance has invalid ID: $id";
            throw new Error\InvalidRequest($message, null);
        }
        $id = Util\Util::utf8($id);
        $base = static::classUrl();
        $extn = urlencode($id);
        return "$base/retrieve/$extn";
    }

    /**
     * @param $id
     * @return string
     * @throws InvalidRequest
     */
    public static function instanceUrlWithId($id)
    {
        $class = get_called_class();
        if ($id === null) {
            $message = "Could not determine which URL to request: "
                . "$class instance has invalid ID: $id";
            throw new Error\InvalidRequest($message, null);
        }
        $id = Util\Util::utf8($id);
        $base = static::classUrl();
        $extn = urlencode($id);
        return "$base/$extn";
    }

    private static function _validateParams($params = null)
    {
        if ($params && !is_array($params)) {
            $message = "You must pass an array as the first argument to JPay API "
               . "method calls.";
            throw new Error\Api($message);
        }
    }

    protected function _request($method, $url, $params = [], $options = null)
    {
        $opts = $this->_opts->merge($options);
        return static::_staticRequest($method, $url, $params, $opts);
    }

    /**
     * @param $method
     * @param $url
     * @param $params
     * @param $options
     * @return array
     * @throws Error\Api
     * @throws Error\Authentication
     */
    protected static function _staticRequest($method, $url, $params, $options)
    {
        $opts = Util\RequestOptions::parse($options);
        $opts->mergeSignOpts(static::$signOpts);


        $queryParams = self::_queryParams();

        $requestor = new ApiRequestor($opts->apiKey, static::baseUrl(), $opts->signOpts);

        $url .= "?".$queryParams;

        list($response, $opts->apiKey) = $requestor->request($method, $url, $params, $opts->headers);
        foreach ($opts->headers as $k => $v) {
            if (!array_key_exists($k, self::$HEADERS_TO_PERSIST)) {
                unset($opts->headers[$k]);
            }
        }
        return [$response, $opts];
    }

    /**
     * @param $params
     * @param null $options
     * @return mixed
     * @throws Error\Api
     * @throws InvalidRequest
     */
    protected static function _retrieve($params, $options = null)
    {

        self::_validateParams($params);
        $url = static::classUrl()."/retrieve/".$params['ch'];

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        return Util\Util::convertToJPayObject($response, $opts);

    }

    /**
     * @param $params
     * @param null $options
     * @return mixed
     * @throws Error\Api
     * @throws InvalidRequest
     */
    protected static function _paid($params, $options = null)
    {

        self::_validateParams($params);
        $url = static::classUrl()."/paid/".$params['ch'];

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        return Util\Util::convertToJPayObject($response, $opts);


    }

    protected static function _all($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('get', $url, $params, $options);
        return Util\Util::convertToJPayObject($response, $opts);
    }

    protected static function _create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl().$params['out_order_no'];

        list($response, $opts) = static::_staticRequest('put', $url, $params, $options);
        return Util\Util::convertToJPayObject($response, $opts);
    }

    protected function _save($options = null)
    {
        $params = $this->serializeParameters();
        if (count($params) > 0) {
            $url = $this->instanceUrl();
            list($response, $opts) = $this->_request('put', $url, $params, $options);
            $this->refreshFrom($response, $opts);
        }
        return $this;
    }

    protected function _delete($params = null, $options = null)
    {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        list($response, $opts) = $this->_request('delete', $url, $params, $options);
        $this->refreshFrom($response, $opts);
        return $this;
    }

    protected static function _directRequest($method, $url, $params = null, $options = null)
    {
        self::_validateParams($params);

        list($response, $opts) = static::_staticRequest($method, $url, $params, $options);
        return Util\Util::convertToJPayObject($response, $opts);
    }
}
