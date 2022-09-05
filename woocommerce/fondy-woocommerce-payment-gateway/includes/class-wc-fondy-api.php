<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 3.0.0
 */
class WC_Fondy_API
{
    const TEST_MERCHANT_ID = 1396424;
    const TEST_MERCHANT_SECRET_KEY = 'test';

    /**
     * @var string Api endpoint url
     */
    private static $ApiUrl = 'https://api.fondy.eu/api/';

    /**
     * @var string Merchant ID
     */
    private static $merchantID;
    /**
     * @var string Secret key
     */
    private static $secretKey;

    /**
     * @return string
     */
    public static function getMerchantID()
    {
        return self::$merchantID;
    }

    /**
     * @param string $merchantID
     */
    public static function setMerchantID($merchantID)
    {
        self::$merchantID = $merchantID;
    }

    /**
     * Define the $SecretKey.
     *
     * @param string $secretKey
     */
    public static function setSecretKey($secretKey)
    {
        self::$secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public static function getSecretKey()
    {
        return self::$secretKey;
    }

    /**
     * @throws Exception
     */
    public static function getCheckoutUrl($requestData)
    {
        $response = self::sendToAPI('checkout/url', $requestData);

        return $response->checkout_url;
    }

    /**
     * @throws Exception
     */
    public static function getCheckoutToken($requestData)
    {
        $response = self::sendToAPI('checkout/token', $requestData);

        return $response->token;
    }

    /**
     * @throws Exception
     */
    public static function reverse($requestData)
    {
        return self::sendToAPI('reverse/order_id', $requestData);
    }

    /**
     * @throws Exception
     */
    public static function capture($requestData)
    {
        return self::sendToAPI('capture/order_id', $requestData);
    }

    /**
     * @throws Exception
     */
    public static function recurring($requestData)
    {
        return self::sendToAPI('recurring', $requestData);
    }

    /**
     * @param $endpoint
     * @param $requestData
     * @return mixed
     * @throws Exception
     */
    public static function sendToAPI($endpoint, $requestData)
    {
        $requestData['merchant_id'] = self::getMerchantID();
        $requestData['signature'] = self::getSignature($requestData, self::getSecretKey());

        $response = wp_safe_remote_post(
            self::$ApiUrl . $endpoint,
            [
                'headers' => ["Content-type" => "application/json;charset=UTF-8"],
                'body' => json_encode(['request' => $requestData]),
                'timeout' => 70,
            ]
        );

        if (is_wp_error($response))
            throw new Exception($response->get_error_message());

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200)
            throw new Exception("Fondy API Return code is $response_code. Please try again later.");

        $result = json_decode($response['body']);

        if (empty($result->response) && empty($result->response->response_status))
            throw new Exception('Unknown Fondy API answer.');

        if ($result->response->response_status != 'success')
            throw new Exception($result->response->error_message);

        return $result->response;
    }

    /**
     * @param $data
     * @param $password
     * @param bool $encoded
     * @return mixed|string
     */
    public static function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $password;
        foreach ($data as $k => $v) {
            $str .= '|' . $v;
        }

        return $encoded ? sha1($str) : $str;
    }

    /**
     * Callback validation process
     *
     * @param $requestBody
     * @throws Exception
     */
    public static function validateRequest($requestBody)
    {
        if (empty($requestBody))
            throw new Exception('Empty request body.');

        if (self::$merchantID != $requestBody['merchant_id'])
            throw new Exception ('Merchant data is incorrect.');

        $requestSignature = $requestBody['signature'];
        unset($requestBody['response_signature_string']);
        unset($requestBody['signature']);
        if ($requestSignature !== self::getSignature($requestBody, self::$secretKey))
            throw new Exception ('Signature is not valid');
    }
}
