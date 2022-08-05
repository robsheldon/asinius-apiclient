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
 *
 * This class converts the SalesPad offset-based pagination into a cursor-
 * based system that is tracked internally. This allows an application to
 * make multiple (different) queries against InventorySearch simultaneously
 * while still keeping track of the offset for each query. The unique cursor
 * value is returned to the Iterator, and the Iterator passes the cursor value
 * back to Inventory's page loader.
 */
class Inventory
{

    protected static    $_inventory_props = [];
    protected static    $_location_props  = [];
    protected static    $_cursors         = [];
    protected static    $_batch_size      = 100;


    /**
     * Process an array of item-and-location results from an InvenorySearch query,
     * and convert them into an array of products with an added "Location" property
     * that includes any values that varied from location to location for that product.
     *
     * This function does not instantiate any Item objects; that step is handled by
     * the Iterator, which should be upstream in the call chain from this function.
     *
     * This function updates the internal $_cursors data structure with the total
     * number of elements received from the last InventorySearch request, so that
     * the next request for the same query will skip the correct number of records.
     *
     * The last product in $entries may not be included in the output because
     * the SalesPad API service may split the same item (but at different locations)
     * across two requests. If a full "page" of results is received, then the
     * last item is excluded from the output (and will be the first item on the
     * next request); if a partial "page" of results is received, then we assume
     * that all data has been received and the last item is returned.
     *
     * @param   string      $cursor_key
     * @param   array       $entries
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    protected static function _squash_inventory (string $cursor_key, array $entries)
    {
        if ( ! array_key_exists($cursor_key, static::$_cursors) ) {
            throw new RuntimeException(sprintf('Internal cursor not found: %s', Asinius::to_str($cursor_key)));
        }
        //  Can't guarantee the order of the elements in $entries, so squashing
        //  them will require two passes.
        $last = '';
        //  Group the entries by Item_Number.
        $squashed = [];
        foreach ($entries as $entry) {
            if ( ! array_key_exists($entry['Item_Number'], $squashed) ) {
                $squashed[$entry['Item_Number']] = [];
            }
            $squashed[$entry['Item_Number']][] = $entry;
            $last = $entry['Item_Number'];
        }
        if ( count($entries) >= static::$_batch_size ) {
            //  Let's assume that the last item received was incomplete.
            //  The API might have 3 locations per item, we ask for 100 results,
            //  so we get 33 individual items and one item from a single location.
            //  No bueno.
            //  I have tested this approach and it seems to be working correctly
            //  so far, but some queries might break it.
            unset($squashed[$last]);
        }
        //  Squash each Item_Number group into a single item with location data
        //  attached to it.
        $received = 0;
        $out = [];
        foreach ($squashed as $item_number => $items) {
            $item = @array_intersect_assoc(...$items);
            $keys = array_fill_keys(array_keys($item), true);
            $item['Locations'] = array_map(function($item) use ($keys){
                return array_intersect_key($item, array_diff_key($item, $keys));
            }, $items);
            $received += count($items);
            $out[] = $item;
        }
        static::$_cursors[$cursor_key]['received'] += $received;
        return $out;
    }


    /**
     * Retrieve the next page of InventorySearch results from the SalesPad API.
     * This is an internal function and is not meant to be used by an application,
     * although it needs to be public in scope. It will throw() a RuntimeException
     * if it is not called by the Iterator class.
     *
     * @param   array       $parameters
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  array
     */
    public static function _load_next_page (array $parameters)
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Iterator');
        if ( ! array_key_exists('cursor', $parameters) ) {
            throw new RuntimeException(sprintf('Did not receive a cursor key in %s', Asinius::to_str($parameters)));
        }
        $cursor_key = $parameters['cursor'];
        if ( ! array_key_exists($cursor_key, static::$_cursors) ) {
            throw new RuntimeException(sprintf('Internal cursor not found: %s', Asinius::to_str($cursor_key)));
        }
        static::$_cursors[$cursor_key]['parameters']['$skip'] = static::$_cursors[$cursor_key]['received'];
        $items = SalesPad::call('/api/InventorySearch', 'GET', static::$_cursors[$cursor_key]['parameters']);
        if ( ! array_key_exists('Items', $items) ) {
            throw new RuntimeException('Unexpected response from server for /api/ItemMaster');
        }
        return static::_squash_inventory($cursor_key, $items['Items']);
    }


    /**
     * Execute an InventorySearch request (OData query string).
     *
     * @param   string      $query
     *
     * @throws  RuntimeException
     *
     * @return  Iterator
     */
    public static function search (string $query = ''): Iterator
    {
        $parameters = ['$top' => sprintf('%d', static::$_batch_size)];
        if ( $query !== '' ) {
            $parameters['$filter'] = $query;
        }
        $items = SalesPad::call('/api/InventorySearch', 'GET', $parameters);
        if ( ! array_key_exists('Items', $items) ) {
            throw new RuntimeException('Unexpected response from server for /api/ItemMaster');
        }
        $items = $items['Items'];
        //  Generate a new cursor for this result set.
        $cursor_key = bin2hex(random_bytes(4));
        static::$_cursors[$cursor_key] = [
            'parameters'    => $parameters,
            'received'      => 0,
        ];
        //  Return an Iterator that will call back to the Inventory class for the rest of the results.
        return new Iterator([static::class, '_load_next_page'], ['cursor' => $cursor_key], '\Asinius\APIClient\SalesPad\Item', static::_squash_inventory($cursor_key, $items));
    }

}
