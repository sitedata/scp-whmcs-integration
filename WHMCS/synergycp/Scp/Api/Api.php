<?php

namespace Scp\Api;

/**
 * Class Responsibilities:
 *  - Encoding HTTP request data for the Synergy API
 *  - Sending HTTP requests
 *  - Storing the output of HTTP responses in ApiResponse
 */
class Api
{
    /**
     * @var Api
     */
    protected static $instance;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $apiKey;

    public function __construct($url, $apiKey)
    {
        $this->url = rtrim($url, '/');
        $this->apiKey = $apiKey;

        static::instance($this);
    }

    /**
     * @param  string $method
     * @param  string $path
     * @param  array [$data]  optional
     *
     * @return ApiResponse
     *
     * @throws ApiError
     */
    public function call($method, $path, array $data = [])
    {
        $headers = array('Content-Type: application/json');
        $url = $this->url($path);
        $postData = "";
        $curl = curl_init();

        switch (strtoupper($method)) {
        case 'GET':
            $url .= '&'.http_build_query($data);
            break;
        default:
            $postData = json_encode($data);
            break;
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => 1,
        ));

        $body = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new ApiError(curl_error($curl));
        }

        curl_close($curl);

        return new ApiResponse($body);
    }

    /**
     * @param  string $path
     * @param  array [$data]  optional
     *
     * @return ApiResponse
     *
     * @throws ApiError
     */
    public function get($path, array $data = [])
    {
        return $this->call('GET', $path, $data);
    }

    /**
     * @param  string $path
     * @param  array [$data]  optional
     *
     * @return ApiResponse
     *
     * @throws ApiError
     */
    public function post($path, array $data = [])
    {
        return $this->call('POST', $path, $data);
    }

    /**
     * @param  string $path
     * @param  array [$data]  optional
     *
     * @return ApiResponse
     *
     * @throws ApiError
     */
    public function patch($path, array $data = [])
    {
        return $this->call('PATCH', $path, $data);
    }

    /**
    * @param  string $path
    * @param  array [$data]  optional
    *
    * @return ApiResponse
    *
    * @throws ApiError
    */
    public function delete($path, array $data = [])
    {
        return $this->call('DELETE', $path, $data);
    }

    public function url($path = '', array $data = [])
    {
        $data += [
            'key' => $this->apiKey,
        ];

        return "$this->url/$path?" . http_build_query($data);
    }

    public static function instance($instance = null)
    {
        return static::$instance = $instance ?: static::$instance;
    }
}
