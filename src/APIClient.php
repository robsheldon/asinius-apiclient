<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient                                                          *
*                                                                              *
*   A static class that provides some helper functions and values for API      *
*   clients.                                                                   *
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

namespace Asinius;


/*******************************************************************************
*                                                                              *
*   \Asinius\APIClient                                                         *
*                                                                              *
*******************************************************************************/

class APIClient
{

    const USER_AGENTS       = [
        'empty'         => '-',                     //  This is curl's default.
        'default'       => 'php/lib-curl; asinius api client library',
        'windows'       => [
            'chrome'    => [
                'latest'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36',
            ],
            'firefox'   => [
                'latest'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:101.0) Gecko/20100101 Firefox/101.0',
            ],
        ],
        'macos'         => [
            'chrome'    => [
                'latest'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36',
            ],
            'firefox'   => [
                'latest'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12.4; rv:101.0) Gecko/20100101 Firefox/101.0',
            ],
            'safari'    => [
                'latest'    => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 12_4) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Safari/605.1.15',
            ],
        ],
        'android'       => [
            'latest'    => 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.78 Mobile Safari/537.36',
        ],
        'ios'           => [
            'latest'    => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.4 Mobile/15E148 Safari/604.1',
        ],
    ];


    public static function get_http_client ()
    {
        $http_client = new HTTP\Client();
        $http_client->setopt(CURLOPT_SSLVERSION, CURL_SSLVERSION_MAX_TLSv1_3);
        $http_client->user_agent(APIClient::USER_AGENTS['default']);
        return $http_client;
    }
}
