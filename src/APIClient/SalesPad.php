<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad                                                 *
*                                                                              *
*   API client for SalesPad / Cavallo systems.                                 *
*                                                                              *
*   SalesPad API documentation can be found at                                 *
*   https://portal.salespad.net/webapi/Help                                    *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius\APIClient;

use Exception, RuntimeException;
use Asinius\Asinius, Asinius\APIClient;

/**
 * \Asinius\APIClient\SalesPad
 *
 * Handles authentication and API requests to the remote server.
 */
class SalesPad
{

    const   SESSION_TEMPORARY       = 1;
    const   SESSION_PERMANENT       = 2;

    protected static $_http_client  = null;
    protected static $_api_host     = '';
    protected static $_session_key  = '';
    protected static $_session_info = [];
    protected static $_last_data    = null;
    protected static $_page_size    = 100;


    /**
     * Send a request to a remote SalesPad API service. This function handles some
     * common request errors. call() will -usually- return an array parsed from a
     * JSON response, but the server may return some other value instead.
     *
     * @param   string      $endpoint
     * @param   string      $method
     * @param   array       $parameters
     * @param   array       $headers
     *
     * @throws  Exception|RuntimeException
     *
     * @return  mixed
     */
    public static function call (string $endpoint, string $method = 'GET', array $parameters = [], array $headers = [])
    {
        if ( static::$_http_client === null ) {
            throw new RuntimeException('SalesPad API is not connected');
        }
        if ( static::$_session_key !== '' ) {
            $headers['Session-ID'] = static::$_session_key;
        }
        try {
            switch ($method) {
                case 'GET':
                    $response = static::$_http_client->get(sprintf('%s%s', static::$_api_host, $endpoint), $parameters, $headers);
                    break;
                case 'POST':
                    $response = static::$_http_client->post(sprintf('%s%s', static::$_api_host, $endpoint), json_encode($parameters, JSON_FORCE_OBJECT), $headers);
                    break;
                default:
                    throw new RuntimeException("Unsupported API call method: $method");
            }
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case 28:
                    throw new RuntimeException('Timeout while trying to connect to ' . static::$_api_host, $e->getCode());
            }
            throw $e;
        }
        if ( $response->empty() ) {
            throw new RuntimeException('The server returned an empty response (no headers or body)');
        }
        static::$_last_data = $response;
        switch ($response->content_type) {
            case 'text/html':
                throw new RuntimeException(sprintf('%s%s returned html, probably an error page', static::$_api_host, $endpoint));
        }
        switch ($response->response_code) {
            case 401:
                throw new RuntimeException(sprintf('You are not authorized to %s %s on %s', $method, $endpoint, static::$_api_host));
        }
        static::$_last_data = $response->body;
        if ( is_array(static::$_last_data) ) {
            if ( array_key_exists('StatusCode', static::$_last_data) ) {
                if ( static::$_last_data['StatusCode'] === 'InternalServerError' ) {
                    if ( array_key_exists('Messages', static::$_last_data) ) {
                        if ( is_array(static::$_last_data['Messages']) && count(static::$_last_data['Messages']) === 1 ) {
                            throw new RuntimeException(sprintf('%s %s returned an Internal Server Error: "%s"', $method, $endpoint, static::$_last_data['Messages'][0]));
                        }
                        throw new RuntimeException(sprintf('%s %s returned an Internal Server Error: "%s"', $method, $endpoint, Asinius::to_str(static::$_last_data['Messages'])));
                    }
                    throw new RuntimeException(sprintf('%s %s returned an Internal Server Error. Further information is not available', $method, $endpoint));
                }
            }
        }
        return static::$_last_data;
    }


    /**
     * Request a new API session from a SalesPad API service.
     *
     * SalesPad supports two types of sessions:
     *
     *     Temporary sessions expire after 15 minutes of activity, and use a
     *     license "seat".
     *
     *     Permanent sessions do not expire and do not use a license seat, but
     *     require a SalesPad "GP API" license. Permanent sessions must store
     *     the session key in the application and re-use it for every connection
     *     to the SalesPad API service.
     *
     *     The default is to request a temporary session from the server, because
     *     this doesn't require the application to manage a session key.
     *
     * @param   string      $host_uri
     * @param   string      $username
     * @param   string      $password
     * @param   int         $type
     *
     * @throws  Exception|RuntimeException
     *
     * @return  boolean
     */
    public static function login (string $host_uri, string $username, string $password, int $type = SalesPad::SESSION_TEMPORARY): bool
    {
        if ( static::$_http_client !== null ) {
            return true;
        }
        $e = null;
        static::$_http_client = APIClient::get_http_client();
        static::$_http_client->setopt(CURLOPT_TIMEOUT, 20);
        //  TODO: Should probably do some kind of test request here to verify
        //  that $host_uri is valid before continuing.
        static::$_api_host = rtrim($host_uri, '/');
        //  SalesPad uses http basic authentication; build the required header
        //  out of the supplied username and password.
        $auth_string = base64_encode(sprintf('%s:%s', $username, $password));
        switch ($type) {
            case static::SESSION_TEMPORARY:
                $endpoint = '/api/Session';
                break;
            case static::SESSION_PERMANENT:
                $endpoint = '/api/Session/Permanent';
                break;
            default:
                static::reset();
                throw new RuntimeException("Invalid session type: $type");
        }
        try {
            $session_info = static::call($endpoint, 'GET', [], ['Authorization' => "Basic $auth_string"]);
        } catch (Exception $e) {
            if ( is_a(static::$_last_data, 'Asinius\HTTP\Response') && static::$_last_data->response_code === 401 ) {
                $e = new RuntimeException('Incorrect username or password during login()');
            }
        }
        if ( $e === null ) {
            if ( is_array($session_info) && array_key_exists('SessionID', $session_info) ) {
                static::$_session_key = $session_info['SessionID'];
                return true;
            }
            $e = new RuntimeException('An unexpected response was returned from SalesPad during login');
        }
        //  Reset the http client and session key, but not the last response from
        //  the server (in case the application wants it).
        $last = static::$_last_data;
        static::reset();
        static::$_last_data = $last;
        //  And finally return the error back to the application.
        throw $e;
    }


    /**
     * Re-authenticate to a SalesPad server using a previous Session ID (probably
     * a permanent session ID; see notes above in ::login()).
     *
     * @param   string      $host_uri
     * @param   string      $session_id
     *
     * @throws  Exception|RuntimeException
     *
     * @return  boolean
     */
    public static function restart_session (string $host_uri, string $session_id): bool
    {
        if ( static::$_http_client !== null ) {
            return true;
        }
        $e  = null;
        static::$_http_client = APIClient::get_http_client();
        static::$_api_host = rtrim($host_uri, '/');
        static::$_session_key = $session_id;
        try {
            $ping_reply = static::call('/api/Session/Ping');
        } catch (Exception $e) {
            if ( is_a(static::$_last_data, 'Asinius\HTTP\Response') && static::$_last_data->response_code === 401 ) {
                $e = new RuntimeException("Your Session ID is not valid or has expired");
            }
        }
        if ( $e === null ) {
            if ( $ping_reply === ['StatusCode' => 'OK', 'ErrorCode' => 0, 'ErrorCodeMessage' => 'No Error', 'Messages' => ['Session is active']] ) {
                return true;
            }
            if ( ! is_array($ping_reply) ) {
                $e = new RuntimeException('An unexpected response was returned from SalesPad during session restart');
            }
            else if ( array_key_exists('Messages', $ping_reply) && $ping_reply['Messages'] === ['Guid should contain 32 digits with 4 dashes (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).'] ) {
                $e = new RuntimeException("The Session ID you provided is not valid");
            }
        }
        //  Reset the http client and session key, but not the last response from
        //  the server (in case the application wants it).
        $last = static::$_last_data;
        static::reset();
        static::$_last_data = $last;
        //  And finally return the error back to the application.
        throw $e;
    }


    /**
     * Reset the current API connection. All further API calls will fail until
     * login() or restart_session() is called successfully.
     *
     * @return  void
     */
    public static function reset ()
    {
        static::$_http_client   = null;
        static::$_api_host      = '';
        static::$_session_key   = '';
        static::$_session_info  = [];
        static::$_last_data     = null;
    }


    /**
     * Return the session ID for the current session.
     *
     * @return  string
     */
    public static function get_session_key (): string
    {
        return static::$_session_key;
    }


    /**
     * Return the last API response data from the server. This is saved by
     * SalesPad::call() and may be converted into an array (if the server sent
     * back a JSON response), but will be otherwise unchanged. Useful for
     * troubleshooting.
     *
     * @return  mixed
     */
    public static function get_last_api_response ()
    {
        return static::$_last_data;
    }


    /**
     * Return the current "page size": the number of entries returned by each
     * API request. May not be supported by all API calls. This should be used
     * by other classes as the '$top' parameter sent to the API.
     *
     * @return  int
     */
    public static function get_page_size (): int
    {
        return static::$_page_size;
    }


    /**
     * Set the current "page size" (number of entries returned by API requests).
     *
     * @param   int     $page_size
     */
    public static function set_page_size (int $page_size)
    {
        static::$_page_size = $page_size;
    }
}
