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

/*******************************************************************************
*                                                                              *
*   \Asinius\APIClient\SalesPad                                                *
*                                                                              *
*******************************************************************************/

class SalesPad
{

    const   SESSION_TEMPORARY       = 1;
    const   SESSION_PERMANENT       = 2;

    protected static $_http_client  = null;
    protected static $_api_host     = '';
    protected static $_session_key  = '';
    protected static $_session_info = [];
    protected static $_last_data    = '';
    protected static $_page_size    = 100;


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
                    if ( in_array($endpoint, ['/api/ItemMaster']) ) {
                        //  Set the default page size for these requests.
                        $parameters['$top'] = static::$_page_size;
                    }
                    $response = static::$_http_client->get(sprintf('%s%s', static::$_api_host, $endpoint), $parameters, $headers);
                    break;
                default:
                    throw new RuntimeException("Unsupported API call method: $method");
            }
        } catch (Exception $e) {
            switch ($e->getCode()) {
                case 28:
                    throw new RuntimeException('Timeout while trying to connect to ' . static::$_api_host, $e->getCode());
                default:
                    throw new RuntimeException($e->getMessage(), $e->getCode());
            }
        }
        static::$_last_data = $response->body;
        switch ($response->content_type) {
            case 'text/html':
                throw new RuntimeException(sprintf('%s%s returned html, probably an error page', static::$_api_host, $endpoint));
        }
        switch ($response->response_code) {
            case 401:
                throw new RuntimeException(sprintf('You are not authorized to %s %s on %s', $method, $endpoint, static::$_api_host));
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
     * @param   string  $host_uri
     * @param   string  $username
     * @param   string  $password
     * @param   int     $type
     *
     * @throws  RuntimeException
     *
     * @return  boolean
     */
    public static function login (string $host_uri, string $username, string $password, int $type = SalesPad::SESSION_TEMPORARY)
    {
        if ( static::$_http_client !== null ) {
            return true;
        }
        static::$_http_client = \Asinius\APIClient::get_http_client();
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
                $session_info = static::call('/api/Session', 'GET', [], ['Authorization' => "Basic $auth_string"]);
                break;
            case static::SESSION_PERMANENT:
                $endpoint = '/api/Session/Permanent';
                $session_info = static::call('/api/Session/Permanent', 'GET', [], ['Authorization' => "Basic $auth_string"]);
                break;
            default:
                static::reset();
                throw new RuntimeException("Invalid session type: $type");
        }
        try {
            $session_info = static::call($endpoint, 'GET', [], ['Authorization' => "Basic $auth_string"]);
        } catch (Exception $e) {
            if ( static::$_last_data->response_code === 401 ) {
                throw new RuntimeException('Incorrect username or password during login()');
            }
            throw new RuntimeException($e->getMessage());
        }
        if ( is_array($session_info) ) {
            if ( array_key_exists('SessionID', $session_info) ) {
                static::$_session_key = $session_info['SessionID'];
                return true;
            }
        }
        throw new RuntimeException('An unexpected response was returned from SalesPad during login');
    }


    /**
     * Re-authenticate to a SalesPad server using a previous Session ID (probably
     * a permanent session ID; see notes above in ::login()).
     *
     * @param  string   $host_uri
     * @param  string   $session_id
     *
     * @throws RuntimeException
     *
     * @return boolean
     */
    public static function restart_session (string $host_uri, string $session_id)
    {
        if ( static::$_http_client !== null ) {
            return;
        }
        static::$_http_client = \Asinius\APIClient::get_http_client();
        static::$_api_host = rtrim($host_uri, '/');
        static::$_session_key = $session_id;
        try {
            $ping_reply = static::call('/api/Session/Ping');
        } catch (Exception $e) {
            if ( static::$_last_data->response_code === 401 ) {
                throw new RuntimeException("Your Session ID is not valid or has expired");
            }
            throw new RuntimeException($e->getMessage());
        }
        if ( is_array($ping_reply) ) {
            if ( $ping_reply === ['StatusCode' => 'OK', 'ErrorCode' => 0, 'ErrorCodeMessage' => 'No Error', 'Messages' => ['Session is active']] ) {
                return true;
            }
            if ( array_key_exists('Messages', $ping_reply) && $ping_reply['Messages'] === ['Guid should contain 32 digits with 4 dashes (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx).'] ) {
                throw new RuntimeException("The Session ID you provided is not valid");
            }
        }
        throw new RuntimeException('An unexpected response was returned from SalesPad during session restart');
    }


    /**
     * Reset the current API connection. All further API calls will fail until
     * login() or restart_session() is called successfully.
     *
     * @return void
     */
    public static function reset ()
    {
        static::$_http_client   = null;
        static::$_api_host      = '';
        static::$_session_key   = '';
        static::$_session_info  = [];
        static::$_last_data     = '';
    }


    public static function get_session_key ()
    {
        return static::$_session_key;
    }


    public static function get_last_api_response ()
    {
        return static::$_last_data;
    }


    public static function get_page_size ()
    {
        return static::$_page_size;
    }


    public static function set_page_size (int $page_size)
    {
        static::$_page_size = $page_size;
    }
}
