<?php

namespace GBasJPay;

use GBasJPay\Error\Authentication;

class ApiRequestor
{
    /**
     * @var string $apiKey The API key that's to be used to make requests.
     */
    public $_apiKey;

    public $_clientId;

    public $_apiMode;

    private $_apiBase;

    private $_signOpts;

    public function __construct($apiKey = null, $apiBase = null, $signOpts = null)
    {
        $this->_apiKey = $apiKey;
        $this->_clientId = GBasJPay::$clientId;
        $this->_signOpts = $signOpts;
        $this->apiBase(); //设置接口地址
    }

    public function apiBase(){
        $this->_apiBase = GBasJPay::$apiLiveBase;
        switch (GBasJPay::$apiMode){
            case "live":
                $this->_apiBase = GBasJPay::$apiLiveBase;
                break;
            case "sandbox":
                $this->_apiBase = GBasJPay::$apiSandboxBase;
                break;
            default:
                $this->_apiBase = GBasJPay::$apiLiveBase;
                break;
        }
    }

    private static function _encodeObjects($d, $is_post = false)
    {
        if ($d instanceof ApiResource) {
            return Util\Util::utf8($d->id);
        } elseif ($d === true && !$is_post) {
            return 'true';
        } elseif ($d === false && !$is_post) {
            return 'false';
        } elseif (is_array($d)) {
            $res = [];
            foreach ($d as $k => $v) {
                $res[$k] = self::_encodeObjects($v, $is_post);
            }
            return $res;
        } else {
            return Util\Util::utf8($d);
        }
    }

    /**
     * @param array $arr An map of param keys to values.
     * @param string|null $prefix (It doesn't look like we ever use $prefix...)
     *
     * @returns string A querystring, essentially.
     */
    public static function encode($arr, $prefix = null)
    {
        if (!is_array($arr)) {
            return $arr;
        }

        $r = [];
        foreach ($arr as $k => $v) {
            if (is_null($v)) {
                continue;
            }

            if ($prefix && $k && !is_int($k)) {
                $k = $prefix."[".$k."]";
            } elseif ($prefix) {
                $k = $prefix."[]";
            }

            if (is_array($v)) {
                $r[] = self::encode($v, $k, true);
            } else {
                $r[] = urlencode($k)."=".urlencode($v);
            }
        }

        return implode("&", $r);
    }

    /**
     * @param $method
     * @param $url
     * @param null $params
     * @param null $headers
     * @return array
     * @throws Authentication
     * @throws Error\Api
     */
    public function request($method, $url, $params = null, $headers = null)
    {
        if (!$params) {
            $params = [];
        }
        if (!$headers) {
            $headers = [];
        }
        list($rbody, $rcode, $myApiKey) = $this->_requestRaw($method, $url, $params, $headers);
        if ($rcode == 502) {
            list($rbody, $rcode, $myApiKey) = $this->_requestRaw($method, $url, $params, $headers);
        }
        $resp = $this->_interpretResponse($rbody, $rcode);
        return [$resp, $myApiKey];
    }


    /**
     * @param $rbody
     * @param $rcode
     * @param $resp
     * @throws Authentication
     * @throws Error\Api
     * @throws Error\InvalidRequest
     * @throws Error\RateLimit
     */
    public function handleApiError($rbody, $rcode, $resp)
    {
        if (!is_object($resp) || !isset($resp->error)) {
            $msg = "Invalid response object from API: $rbody "
                ."(HTTP response code was $rcode)";
            throw new Error\Api($msg, $rcode, $rbody, $resp);
        }

        $error = $resp->error;
        $msg = isset($error->message) ? $error->message : null;
        $param = isset($error->param) ? $error->param : null;
        $code = isset($error->code) ? $error->code : null;

        switch ($rcode) {
            case 429:
                throw new Error\RateLimit(
                    $msg,
                    $param,
                    $rcode,
                    $rbody,
                    $resp
                );
            case 400:
            case 404:
                throw new Error\InvalidRequest(
                    $msg,
                    $param,
                    $rcode,
                    $rbody,
                    $resp
                );
            case 401:
                throw new Error\Authentication($msg, $rcode, $rbody, $resp);
            default:
                throw new Error\Api($msg, $rcode, $rbody, $resp);
        }
    }

    private function _requestRaw($method, $url, $params, $headers)
    {
        $myApiKey = $this->_apiKey;
        if (!$myApiKey) {
            $myApiKey = GBasJPay::$apiKey;
        }

        if (!$myApiKey) {
            $msg = 'No API key provided.  (HINT: set your API key using '
                . '"GBasJPay::setApiKey(<API-KEY>)".  You can generate API keys from '
                . 'the GBasJPay web interface.  See GBasJPay api doc for '
                . 'details.';
            throw new Error\Authentication($msg);
        }

        $myClientId = $this->_clientId;
        if (!$myClientId) {
            $myClientId = GBasJPay::$clientId;
        }

        if (!$myClientId) {
            $msg = 'No API clientId provided.  (HINT: set your clientId using '
                . '"GBasJPay::setClientId(<CLIENT-ID>)".  You can generate clientId from '
                . 'the GBasJPay web interface.  See GBasJPay api doc for '
                . 'details.';
            throw new Error\Authentication($msg);
        }


        $absUrl = $this->_apiBase . $url;

        $params = self::_encodeObjects($params, $method == 'post' || $method == 'put');

        $defaultHeaders = [];
        if ($method == 'post' || $method == 'put') {
            $defaultHeaders['Content-type'] = 'application/json;charset=UTF-8';
            $defaultHeaders['Accept'] = 'application/json;charset=UTF-8';
        }

        $combinedHeaders = array_merge($defaultHeaders, $headers);

        $rawHeaders = [];

        foreach ($combinedHeaders as $header => $value) {
            $rawHeaders[] = $header . ': ' . $value;
        }

        list($rbody, $rcode) = $this->_curlRequest(
            $method,
            $absUrl,
            $rawHeaders,
            $params
        );
        return [$rbody, $rcode, $myApiKey, $myClientId];
    }

    private function _interpretResponse($rbody, $rcode)
    {
        try {
            $resp = json_decode($rbody);
        } catch (\Exception $e) {
            $msg = "Invalid response body from API: $rbody "
                . "(HTTP response code was $rcode)";
            throw new Error\Api($msg, $rcode, $rbody);
        }

        if ($rcode < 200 || $rcode >= 300) {
            $this->handleApiError($rbody, $rcode, $resp);
        }
        return $resp;
    }

    private function _curlRequest($method, $absUrl, $headers, $params)
    {

        $curl = curl_init();
        $method = strtolower($method);
        $opts = [];
        $dataToBeSign = '';
        $requestTime = null;
        if ($method === 'get' || $method === 'delete') {
            if ($method === 'get') {
                $opts[CURLOPT_HTTPGET] = 1;
            } else {
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            }
            $dataToBeSign .= parse_url($absUrl, PHP_URL_PATH);
            if (count($params) > 0) {
                $encoded = self::encode($params);
                $absUrl = "$absUrl?$encoded";
                $dataToBeSign .= '?' . $encoded;
            }
            $requestTime = time();
        } elseif ($method === 'post' || $method === 'put') {
            if ($method === 'post') {
                $opts[CURLOPT_POST] = 1;
            } else {
                $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
            }
            $rawRequestBody = $params !== null ? json_encode($params) : '';
            $opts[CURLOPT_POSTFIELDS] = $rawRequestBody;
            $dataToBeSign .= $rawRequestBody;
            if ($this->_signOpts !== null) {
                if (isset($this->_signOpts['uri']) && $this->_signOpts['uri']) {
                    $dataToBeSign .= parse_url($absUrl, PHP_URL_PATH);
                }
                if (isset($this->_signOpts['time']) && $this->_signOpts['time']) {
                    $requestTime = time();
                }
            }
        } else {
            throw new Error\Api("Unrecognized method $method");
        }

        $absUrl = Util\Util::utf8($absUrl);
        $opts[CURLOPT_URL] = $absUrl;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $opts[CURLOPT_CONNECTTIMEOUT] = 30;
        $opts[CURLOPT_TIMEOUT] = 80;
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($curl, $opts);
        $rbody = curl_exec($curl);

        if (!defined('CURLE_SSL_CACERT_BADFILE')) {
            define('CURLE_SSL_CACERT_BADFILE', 77);  // constant not defined in PHP
        }
        
        if ($rbody === false) {
            $errno = curl_errno($curl);
            $message = curl_error($curl);
            curl_close($curl);
            $this->handleCurlError($errno, $message);
        }

        $rcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if(GBasJPay::$debug){
            echo "=====url======\r\n";
            echo ($absUrl)."\r\n";

            echo "=====post data======\r\n";
            print_r($params)."\r\n";;

            echo "=====headers======\r\n";
            print_r($headers)."\r\n";;

            echo '=====request info====='."\r\n";
            print_r( curl_getinfo($curl) )."\r\n";;

            echo '=====response code====='."\r\n";
            echo( $rcode )."\r\n";;

            echo '=====response====='."\r\n";
            echo ( $rbody )."\r\n";;
        }



        curl_close($curl);
        return [$rbody, $rcode];
    }

    /**
     * @param $errno
     * @param $message
     * @throws Error\ApiConnection
     */
    public function handleCurlError($errno, $message)
    {
        $apiBase = $this->_apiBase;
        switch ($errno) {
            case CURLE_COULDNT_CONNECT:
            case CURLE_COULDNT_RESOLVE_HOST:
            case CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect toGBasJPay ($apiBase).  Please check your "
                . "internet connection and try again.  If this problem persists, "
                . "you should check GBasJPay's service status ";

                break;
            case CURLE_SSL_CACERT:
            case CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verifyGBasJPay's SSL certificate.  Please make sure "
                . "that your network is not intercepting certificates.  "
                . "(Try going to $apiBase in your browser.)";
                break;
            default:
                $msg = "Unexpected error communicating with GBasJPay.";
        }

        $msg .= "\n\n(Network error [errno $errno]: $message)";
        throw new Error\ApiConnection($msg);
    }

    private function privateKey()
    {
        if (!GBasJPay::$privateKey) {
            if (!GBasJPay::$privateKeyPath) {
                return null;
            }
            if (!file_exists(GBasJPay::$privateKeyPath)) {
                throw new Error\Api('Private key file not found at: ' . GBasJPay::$privateKeyPath);
            }
            GBasJPay::$privateKey = file_get_contents(GBasJPay::$privateKeyPath);
        }
        return GBasJPay::$privateKey;
    }
}
