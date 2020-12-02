<?php
class FondyForm
{
    const API_CHECKOUT_URL = 'https://api.fondy.eu/api/checkout/url/';
    const TEST_MERCHANT_ID = 1396424;
    const TEST_MERCHANT_KEY = 'test';
    const RESPONCE_SUCCESS = 'success';
    const RESPONCE_FAIL = 'failure';
    const ORDER_SEPARATOR = '#';
    const SIGNATURE_SEPARATOR = '|';
    const ORDER_APPROVED = 'approved';
    const ORDER_DECLINED = 'declined';

    public static function getSignature($data, $secretKey)
    {
        return sha1($secretKey . FondyForm::SIGNATURE_SEPARATOR . base64_encode(json_encode(array('order' => $data))));
    }

    public static function isPaymentValid($fondySettings, $response , $base64_data, $sign)
    {
        if ($fondySettings['merchant_id'] != $response['merchant_id']) {
		
            return 'An error has occurred during payment. Merchant data is incorrect.';
        }
		if (isset($response['response_signature_string'])){
			unset($response['response_signature_string']);
		}
		if (isset($response['signature'])){
			unset($response['signature']);
		}
	    if ($sign != sha1($fondySettings['secret_key'] . FondyForm::SIGNATURE_SEPARATOR . $base64_data)) {
		    return 'An error has occurred during payment. Signature is not valid.';
	    }
        return true;
    }
}