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
use Asinius\APIClient\SalesPad\Price_Level;
use Asinius\APIClient\SalesPad\Iterator;

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
    protected           $_line_items    = null;


    public static function create (Customer $customer, string $type, Price_Level $price_level, array $properties = [])
    {
        if ( static::$_endpoint === '' ) {
            throw new RuntimeException(sprintf('%s::create() is not implemented', static::class));
        }
        if ( ! in_array($type, ['QUOTE', 'ORDER', 'INVOICE', 'RETURN', 'BACKORDER', 'FULFILLMENT']) ) {
            throw new RuntimeException(sprintf('%s::create(): %s is not a valid SalesDocument type', static::class, $type));
        }
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
                'Price_Level'       => $price_level->name,
            ]
        );
        return parent::create($values);
    }


    public function line_items ()
    {
        if ( $this->_line_items === null ) {
            //  Well, "/api/SalesLineItem/[Sales_Doc_Type]/[Sales_Doc_Num]" doesn't
            //  work, which makes it kind of useless. Use an OData query instead:
            $sales_id = $this->unmapped('Sales_Doc_Num');
            $query = sprintf("Sales_Doc_Num eq '%s'", $sales_id);
            $results = SalesPad::call('/api/SalesLineItem', 'GET', ['$filter' => $query]);
            if ( ! isset($results['Items']) ) {
                throw new RuntimeException(sprintf('Failed to load line items for Sales Document "%s"', $sales_id));
            }
            //  The results aren't being paged, so the Iterator is temporary:
            $this->_line_items = iterator_to_array(new Iterator(null, [], 'Asinius\APIClient\SalesPad\SalesLineItem', $results['Items']));
        }
        return $this->_line_items;
    }

}
