<?php
namespace Transact\Admin\Api;

/**
 * Class TransactApi
 */
class TransactApi
{
    /**
     * Account ID key used on config.ini
     */
    const ACCOUNT_ID_KEY = '{{account_id}}';

    /**
     * Account ID key used on config.ini
     */
    const DIGEST_ID_KEY = '{{digest}}';


    /**
     * Validates publisher settings against transact.io service
     *
     * @param $validate_url
     * @param $account_id
     * @param $secret
     * @return bool
     */
    public function validates($validate_url, $account_id, $secret)
    {
        $search = array (
            self::ACCOUNT_ID_KEY,
            self::DIGEST_ID_KEY
        );

        $replace = array (
            $account_id,
            $this->digest($secret)
        );

        $url = str_replace($search, $replace, $validate_url);
        $response = $this->curl_get($url);

        if ($response['code'] == 200) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Validate subscription against transact.io
     *
     * @param $validate_url
     * @param $account_id
     * @return bool
     */
    public function subscriptionValidates($validate_url, $account_id)
    {
        $url = str_replace(self::ACCOUNT_ID_KEY, $account_id, $validate_url);
        $response = $this->curl_get($url);

        /**
         * query show an array of two elements (monthly and anual subscription)
         */
        if ($response['code'] == 200 && (count(json_decode($response['string'])) > 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * It creates the digest to validate
     * todo: right now second parameter is hardcoded to test
     *
     * @param $secret
     * @return string
     */
    public function digest($secret)
    {
        return hash_hmac('sha256', 'test', $secret);
    }

    /**
     * Simple curl GET call
     *
     * @param $url
     * @return array [string|code] it returns response as string and http response code
     */
    public function curl_get($url)
    {
        $output = array();
        // create curl resource
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_URL, $url);
        $output['string'] = curl_exec($ch);
        $output['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $output;
    }

}

