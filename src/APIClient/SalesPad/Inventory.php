<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\Inventory                                       *
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

use Asinius\Asinius, Asinius\APIClient\SalesPad, Asinius\APIClient\SalesPad\Item, RuntimeException;

/**
 * \Asinius\APIClient\SalesPad\Inventory
 *
 * Adds support for stock levels and other inventory-related data in Item objects.
 *
 * According to SalesPad (Cavallo) staff, the InventorySearch endpoint
 * (https://portal.salespad.net/webapi/Help#InventorySearch) was intended to
 * replace the ItemMaster endpoint. Unlike ItemMaster however, InventorySearch
 * returns one entry for each location-and-item. If the database has four
 * locations configured, and you want to retrieve details for 100 items, you'll
 * have to spin through 400 API results.
 *
 * This class -somewhat- solves that, or at least papers over it a bit, by
 * injecting inventory-related data into Item objects and returning the same
 * Iterator as the Item class does.
 *
 * If you don't need stock details, use the Item class. If you do, use this
 * class. This class will still need to abuse the SalesPad API, but at least
 * the application code will be a bit easier to work with.
 */
class Inventory
{

    protected static    $_inventory_props = [];
    protected static    $_location_props  = [];

    public static function search (string $query = ''): Iterator
    {
        $parameters = ['$top' => '100'];
        if ( $query !== '' ) {
            $parameters['$filter'] = $query;
        }
        $items = SalesPad::call('/api/InventorySearch', 'GET', $parameters);
        if ( ! array_key_exists('Items', $items) ) {
            throw new RuntimeException('Unexpected response from server for /api/ItemMaster');
        }
        $items = $items['Items'];
        if ( count($items) > 0 && empty(static::$_inventory_props) ) {
            //  An additional API request needs to be made here so that ItemMaster
            //  properties can be separated from InventorySearch properties.
            //  Select a test item from the search results.
            $test_item = trim($items[0]['Item_Number']);
            $item_master = SalesPad::call('/api/ItemMaster', 'GET', ['$filter' => "Item_Number eq '$test_item'"]);
            if ( ! array_key_exists('Items', $item_master) || count($item_master['Items']) !== 1 ) {
                throw new RuntimeException("There was an error retreiving item $test_item from /api/ItemMaster");
            }
            //  This next line finds all the array keys that are present in
            //  InventorySearch results but not ItemMaster results.
            static::$_inventory_props = array_diff(array_keys($items[0]), array_intersect(array_keys($item_master['Items'][0]), array_keys($items[0])));
        }
        return new Iterator('/api/InventorySearch', $parameters, [static::class, '_new_item'], $items);
    }


    public static function _new_item (array $item)
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Iterator');
        return new Item($item);
        echo "_new_item:\n";
        var_dump($item);
        die();
    }

}

/*
    Here's how to fix the InventorySearch -> Iterator -> ItemMaster problem:

    1. change the Iterator constructor so that $endpoint can be a callable function.
    2. Inventory::search() will return an Iterator that's configured to call Inventory::_load_next_page()
    3. Inventory::_load_next_page will receive $parameters (passed from the Iterator's constructor)
        which will be something like ['cursor' => 'randomstring']
    4. Inventory::_load_next_page will use the cursor to load up a set of stored values for the
        request endpoint, the number of items to skip, etc., and then will proceed as normal.
 */