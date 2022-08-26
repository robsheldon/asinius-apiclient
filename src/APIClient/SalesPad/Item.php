<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\Item                                            *
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

namespace Asinius\APIClient\SalesPad;

use RuntimeException;
use Asinius\Asinius;
use Asinius\APIClient\SalesPad;
use Asinius\APIClient\SalesPad\CommonObject;

/**
 * \Asinius\APIClient\SalesPad\Item
 *
 * Encapsulate products in a SalesPad database using the ItemMaster endpoint.
 */
class Item extends CommonObject
{

    protected static    $_endpoint      = '/api/ItemMaster';
    protected static    $_id_key        = 'Item_Number';
    protected static    $_short_name    = 'Item';
    protected static    $_field_maps    = [];


    /**
     * Create a new item in the remote database. This function is not yet implemented.
     *
     * @param   array   $values
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public static function create (array $values)
    {
        throw new RuntimeException(static::class . '::create() is not implemented');
    }

}
