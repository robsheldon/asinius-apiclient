<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\Customer                                        *
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
 * \Asinius\APIClient\SalesPad\Customer
 *
 * Encapsulate customers in a SalesPad database.
 */
class Customer extends CommonObject
{

    protected static    $_endpoint      = '/api/Customer';
    protected static    $_id_key        = 'Customer_Num';
    protected static    $_short_name    = 'Customer';
    protected static    $_field_maps    = [];


    /**
     * Create a new customer in the remote database. This appears t work with
     * just the customer name as the only required value (?). SalesPad returns
     * a customer entry with an assigned customer number, which is nice.
     *
     * @param   string  $name
     * @param   array   $properties
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public static function create (string $name, array $properties = [])
    {
        if ( static::$_endpoint === '' ) {
            throw new RuntimeException(sprintf('%s::create() is not implemented', static::class));
        }
        if ( $name === '' ) {
            throw new RuntimeException(sprintf('%s::create(): customer name is required and cannot be empty', static::class));
        }
        $properties['Customer_Name'] = $name;
        return parent::create($properties);
    }

}
