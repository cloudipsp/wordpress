<?php

class Fondy_API
{
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';
    const ORDER_SEPARATOR = '#';
    const SIGNATURE_SEPARATOR = '|';
    const URL = "https://api.fondy.eu/api/checkout/redirect/";

    public static function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);
        $str = $password;
        foreach ($data as $k => $v) {
            $str .= self::SIGNATURE_SEPARATOR . $v;
        }
        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    public static function isPaymentValid($fondySettings, $response, $base64_data, $sign)
    {
        if ($fondySettings['mid'] != $response['merchant_id']) {
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }
        if (isset($response['response_signature_string'])) {
            unset($response['response_signature_string']);
        }
        if (isset($response['signature'])) {
            unset($response['signature']);
        }
        if (isset($response['merchant_data'])) {
            $response['merchant_data'] = stripslashes($response['merchant_data']);
        }
        if (!empty($base64_data)) {
            if ($sign != sha1($fondySettings['secret_key'] . '|' . $base64_data)) {
                return 'An error has occurred during payment. Signature is not valid.';
            }
        } elseif ($sign != self::getSignature($response, $fondySettings['secret_key'])) {
            return 'An error has occurred during payment. Signature is not valid.';
        }

        return true;
    }
}