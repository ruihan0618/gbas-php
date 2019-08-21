<?php
namespace GBasJPay;

use GBasJPay\Error\SignatureVerification;

abstract class Webhook
{
    const DEFAULT_TOLERANCE = 300;

    public static function constructEvent($payload)
    {
        $verifySignObject = WebhookSignature::verifyObject($payload);
        if($verifySignObject === true){
            return $payload;
        }else{
            throw new SignatureVerification(
                $verifySignObject,'',$payload
            );
        }
    }
}