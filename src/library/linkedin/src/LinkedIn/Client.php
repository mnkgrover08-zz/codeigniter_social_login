<?php
class Client {
    public $domain = "https://api.linkedin.com";
    /**
     * Authorization and AccessToken api endpoints are special in that they live on www.linkedin.com not api.linkedin.com
     */
    public $authorizationUrl = "https://www.linkedin.com/uas/oauth2/authorization";
    public $accessTokenUrl = "https://www.linkedin.com/uas/oauth2/accessToken";

    /**
     * Curl handle
     *
     * @var resource
     */
    protected $curl;

    /**
     * Authorized user's access token (provided at the end of the auhorization process)
     *
     * @var string
     */
    protected $access_token;

    /**
     * API key provided by LinkedIn for your application
     *
     * @var string
     */
    protected $api_key;

    /**
     * API secret provided by LinkedIn for your application
     *
     * @var string
     */
    protected $api_secret;

    public function __construct($api_key, $api_secret, $curl = null) {
        $this->setApiCredentials($api_key, $api_secret);
        $this->setCurl($curl);
    }

    /**
     * Performs a request on a specified URI using an access token
     *
     * @param string $resource The relative URI for the resource requested (e.g. "/v1/people/~:(firstName,lastName)")
     * @param array $payload
     * @param string $method
     * @param array $type Headers : multipart/form, application/json, application/xml
     * @return array
     */
    public function fetch($resource, array $payload = array(), $method = 'GET', $type = array('Content-type: multipart/form-data')) {
        $url = $this->domain . $resource;

        $payload = array('oauth2_access_token' => $this->getAccessToken(), 'format' => 'json')
                + $payload;
        return $this->_request($url, $payload, $method, $type);
    }

    /**
     * Returns the fully qualified authorization url to redirect the client
     *
     * @param $redirect_uri
     * @param null|string $state
     * @param null|string $scope
     * @return string
     */
    public function getAuthorizationUrl($redirect_uri, $state = 'NOSTATE', $scope = null) {
        $params = array(
            'response_type' => 'code',
            'client_id'     => $this->getApiKey(),
            'redirect_uri'  => $redirect_uri,
            'state'         => $state,
            'scope'         => $scope,
        );

        return $this->authorizationUrl . '?' . http_build_query($params);
    }

    /**
     * Confirms the verification code and redirect URI and produces an array containing the access token, will also set
     * the access token internally if one was properly returned
     *
     * @param string $verification_code the code provided by LinkedIn
     * @param string $redirect_uri the exact redirecturi used in the getAuthorizationUrl step
     * @return array
     */
    public function fetchAccessToken($verification_code, $redirect_uri) {
        $params = array(
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->getApiKey(),
            'client_secret' => $this->getApiSecret(),
            'code'          => $verification_code,
            'redirect_uri'  => $redirect_uri,
        );

        $url = $this->accessTokenUrl . '?' . http_build_query($params);

        $response = $this->_request($url, array(), 'GET');

        $response['expires_at'] = time() + $response['expires_in'] - 3600; //Give 1 hour of fudge time for renewal

        if (isset($response['access_token'])) {
            $this->setAccessToken($response['access_token']);
        }

        return $response;
    }


    /**
     * @param string $url full url
     * @param array $payload Payload values to passed in through GET or POST parameters
     * @param string $method HTTP method for request (GET, PUT, POST, ...)
     * @param array $type Headers : multipart/form, application/json, application/xml
     * @return array JSON-decoded response
     * @throws Exception
     */

    protected function _request($url, array $payload = array(), $method = 'GET', $type = array('Content-type: multipart/form-data')) {
        $ch = $this->getCurl();
        if(!empty($payload['oauth2_access_token'])){
            $url = $url.'?oauth2_access_token='.$payload['oauth2_access_token'];            
        }
        if (!empty($payload) && $method == 'GET') {
            $url .= "&" . http_build_query($payload);
        }
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
        ));
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $type);
                if (!empty($payload) && stripos($type[0], "multipart/form-data")) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                } 
                else if (!empty($payload) && stripos($type[0], "application/xml")) {
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload['message']);
                    curl_setopt(
                            $ch, 
                            CURLOPT_HTTPHEADER, 
                            array(
                                $type[0],
                                'Content-length: ' . strlen($payload['message'])
                            )
                    );
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: multipart/form-data'));
                if (!empty($payload))
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_POST, false);
                break;
            default:
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        }

        $body = curl_exec($ch);
        
        $errno = curl_errno($ch);
        if ($errno !== 0) {
            throw new Exception(sprintf("Error connecting to LinkedIn: [%s] %s", $errno, curl_error($ch)), $errno);
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code >= 400) {
            throw new Exception(trim(strip_tags($body)), $code);
        }

        $response = json_decode($body, true);

        if (isset($response['error'])) {
            throw new Exception(sprintf("%s: %s", $response['error'], $response['error_description']), $code);
        }

        return $response;
    }


    /**
     * @param string $access_token
     */
    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * @param string $api_key
     * @param string $api_secret
     */
    public function setApiCredentials($api_key, $api_secret)
    {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @return string
     */
    public function getApiSecret()
    {
        return $this->api_secret;
    }

    /**
     * @param resource $curl
     */
    public function setCurl($curl) {
        $this->curl = $curl;
    }

    /**
     * @return resource
     */
    public function getCurl() {
        if (!is_resource($this->curl)) {
            $this->curl = curl_init();

            curl_setopt_array($this->curl, array(
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS      => 1,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 30,
            ));
        }

        return $this->curl;
    }
}