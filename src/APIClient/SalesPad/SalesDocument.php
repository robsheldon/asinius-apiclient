<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\SalesDocument                                   *
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
use Asinius\APIClient\SalesPad\Customer;

/**
 * \Asinius\APIClient\SalesPad\SalesDocument
 *
 * Query and update SalesDocument (order) entries in a SalesPad database.
 */
class SalesDocument extends CommonObject
{

    protected static    $_endpoint      = '/api/SalesDocument';
    protected static    $_id_key        = 'Sales_Doc_Num';
    protected static    $_short_name    = 'SalesDocument';
    protected static    $_field_maps    = [];


    public static function create (Customer $customer, string $type, string $price_level, array $properties = [])
    {
        if ( static::$_endpoint === '' ) {
            throw new RuntimeException(sprintf('%s::create() is not implemented', static::class));
        }
        if ( ! in_array($type, ['QUOTE', 'ORDER', 'INVOICE', 'RETURN', 'BACKORDER', 'FULFILLMENT']) ) {
            throw new RuntimeException(sprintf('%s::create(): %s is not a valid SalesDocument type', static::class, $type));
        }
        //  $price_level must match a pre-defined value in the database.
        //  (You cannot define your own price levels here.)
        //  TODO: Implement the PriceLevel endpoint and cache available price
        //  levels before creating a sales order.
        //  For now, let's proceed with creating this order without making an
        //  extra API call to verify that the customer exists.
        $values = array_merge(
            [
                //  Defaults.
                'Customer_Name'     => $customer->unmapped('Customer_Name'),
                //  Sales_Doc_ID is a required value; this is a placeholder
                //  until we can get better info on the proper values to use.
                'Sales_Doc_ID'      => 'ORD',
            ],
            //  Application-provided. Applications can override defaults here.
            $properties,
            [
                //  Required:
                'Customer_Num'      => $customer->unmapped('Customer_Num'),
                'Sales_Doc_Type'    => $type,
                'Price_Level'       => $price_level,
            ]
        );
        return parent::create($values);
    }

}
