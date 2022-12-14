<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\CustomerAddress                                 *
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

/*
    Example CustomerAddress record:
          Customer_Num: "000016"
          Address_Code: "PRIMARY"
           Address_Use: ""
       Sales_Person_ID: ""
       Shipping_Method: ""
              UPS_Zone: ""
          Tax_Schedule: ""
      Alt_Company_Name: "Rob Sheldon"
        Contact_Person: ""
        Address_Line_1: ""
        Address_Line_2: ""
        Address_Line_3: ""
               Country: ""
          Country_Code: ""
                  City: ""
                 State: ""
                   Zip: ""
               Phone_1: ""
               Phone_2: ""
               Phone_3: ""
                   Fax: ""
           Modified_On: "1900-01-01T00:00:00.000"
            Created_On: "2022-09-22T00:00:00.000-04:00"
               Comment: ""
                 Email: ""
              Email_To: ""
              Email_CC: ""
             Email_BCC: ""
              Web_Site: ""
                 Login: ""
              Password: ""
        Warehouse_Code: ""
              USERDEF1: ""
              USERDEF2: ""
            Dont_Email: bool(false)
             Dont_Mail: bool(false)
       Sales_Territory: ""
              Dont_Fax: bool(false)
     Address_Validated: bool(false)
Address_Classification: ""
       File_Location_1: ""
       File_Location_2: ""
            DEX_ROW_TS: "2022-09-22T20:56:39.110"
         Notifications: []
             xShowroom: bool(false)
 */

namespace Asinius\APIClient\SalesPad;

use RuntimeException;
use Asinius\Asinius;
use Asinius\APIClient\SalesPad;
use Asinius\APIClient\SalesPad\CommonObject;

/**
 * \Asinius\APIClient\SalesPad\CustomerAddress
 *
 * Store and manage customer addresses in a SalesPad database.
 */
class CustomerAddress extends CommonObject
{

    protected static    $_endpoint      = '/api/CustomerAddr';
    protected static    $_id_key        = 'Address_Code';
    protected static    $_short_name    = 'CustomerAddress';
    protected static    $_field_maps    = [];


    /**
     * Create a new address for a specific customer record.
     *
     * SalesPad allows us to create empty customer addresses with just a customer
     * number and an address code. I don't like that, but I'm not going to force
     * applications to provide optional values either.
     *
     * This function can only be called by a Customer object (to ensure that
     * an existing Customer_Num is provided), so use Customer->add_address() to
     * call this function.
     *
     * @param   string      $customer_number
     * @param   string      $address_code
     * @param   array       $properties
     *
     * @return  CustomerAddress
     */
    public static function create (string $customer_number, string $address_code, array $properties = [])
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Customer');
        $properties['Customer_Num'] = $customer_number;
        $properties['Address_Code'] = $address_code;
        return parent::create($properties);
    }
}
