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

/*
    Example customer record:
                   Customer_Num: "000016"
                  Customer_Name: "Rob Sheldon"
                 Customer_Class: ""
         Corporate_Customer_Num: ""
                     Short_Name: ""
                 Statement_Name: ""
              Primary_Addr_Code: ""
      Primary_Bill_To_Addr_Code: ""
      Primary_Ship_To_Addr_Code: ""
         Statement_To_Addr_Code: ""
                Sales_Person_ID: ""
                Sales_Territory: ""
                  Payment_Terms: ""
                Shipping_Method: ""
                    Price_Level: ""
                     User_Def_1: ""
                     User_Def_2: ""
                   Tax_Exempt_1: ""
                   Tax_Exempt_2: ""
           Tax_Registration_Num: ""
                      Comment_1: ""
                      Comment_2: ""
              IntegrationSource: int(0)
                       Inactive: bool(false)
                        On_Hold: bool(false)
                           Note: "Test account"
                    Currency_ID: "Z-US$"
                   Currency_Dec: int(2)
                      Last_Aged: "1900-01-01T00:00:00.000Z"
                        Balance: float(0)
               Unapplied_Amount: float(0)
          Customer_Credit_Limit: float(0)
                  Last_Pay_Date: "1900-01-01T00:00:00.000Z"
                   Last_Pay_Amt: float(0)
             First_Invoice_Date: "1900-01-01T00:00:00.000Z"
              Last_Invoice_Date: "1900-01-01T00:00:00.000Z"
               Last_Invoice_Amt: float(0)
                 Last_Stmt_Date: "1900-01-01T00:00:00.000Z"
                  Last_Stmt_Amt: float(0)
                  Life_Avg_Days: int(0)
                  Year_Avg_Days: int(0)
       Total_Amt_NSF_Checks_YTD: float(0)
             Num_NSF_Checks_YTD: int(0)
                   Tax_Schedule: ""
                  Ship_Complete: bool(false)
                  Stmt_Email_To: ""
                  Stmt_Email_CC: ""
                 Stmt_Email_BCC: ""
                       Email_To: "rob@robsheldon.com"
                       Email_CC: ""
                      Email_BCC: ""
                        Message: ""
                       USERDEF1: ""
                       USERDEF2: ""
                 Trade_Discount: float(0)
             Master_Distributor: ""
              Method_Of_Billing: int(0)
          Send_Email_Statements: int(0)
                     Created_On: "2022-09-01T00:00:00.000-04:00"
                     Changed_On: "2022-09-01T00:00:00.000-04:00"
    Promotions_Applied_Customer: ""
                     DEX_ROW_TS: "2022-09-01T00:00:00.000-04:00"
                On_Order_Amount: float(0)
              Credit_Limit_Type: int(0)
            Finance_Charge_Type: int(0)
             Finance_Charge_Amt: float(0)
             Finance_Charge_Pct: int(0)
                   Min_Pmt_Type: int(0)
                    Min_Pmt_Amt: float(0)
                    Min_Pmt_Pct: int(0)
                   Balance_Type: int(0)
              Max_Writeoff_Type: int(0)
               Max_Writeoff_Amt: float(0)
                  Notifications: []
                   Created_Date: "1900-01-01T00:00:00.000Z"
                       Ship_Day: ""
 */

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
    protected           $_addresses     = null;


    /**
     * Create a new customer in the remote database. This appears to work with
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


    public function get_addresses ()
    {
        if ( $this->_addresses === null ) {
            $query = sprintf("%s eq '%s'", static::$_id_key, $this->unmapped(static::$_id_key));
            $endpoint = '/api/CustomerAddr';
            $results = SalesPad::call($endpoint, 'GET', ['$filter' => $query]);
            if ( ! isset($results['Items']) ) {
                throw new RuntimeException(sprintf('%s %s failed for %s "%s"', 'GET', $endpoint, static::$_short_name, $this->_id));
            }
            $this->_addresses = iterator_to_array(new Iterator(null, [], 'Asinius\APIClient\SalesPad\CustomerAddress', $results['Items']));
        }
        return $this->_addresses;
    }

}
