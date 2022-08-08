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

/**
 * \Asinius\APIClient\SalesPad\Item
 *
 * Encapsulate products in a SalesPad database using the ItemMaster endpoint.
 */
class Item
{

    protected static    $_field_maps    = [];
    protected           $_id            = '';
    protected           $_properties    = [];


    /**
     * Configure automatic translation of named properties in Item objects
     * returned by the SalesPad API.
     *
     * @param   array       $fields
     *
     * @return  void
     */
    public static function map (array $fields)
    {
        static::$_field_maps = $fields;
    }


    /**
     * Retrieve a list of items from the API, using the ItemMaster endpoint,
     * with an optional search query. The search query needs to be an OData
     * query string. See also the OData section at
     * https://portal.salespad.net/webapi/GettingStarted.
     *
     * @param   string      $query
     *
     * @throws  RuntimeException
     *
     * @return  Iterator
     */
    public static function search (string $query = ''): Iterator
    {
        $parameters = ['$top' => sprintf('%d', SalesPad::get_page_size())];
        if ( $query !== '' ) {
            $parameters['$filter'] = $query;
        }
        $items = SalesPad::call('/api/ItemMaster', 'GET', $parameters);
        if ( ! array_key_exists('Items', $items) ) {
            throw new RuntimeException('Unexpected response from server for /api/ItemMaster');
        }
        return new Iterator('/api/ItemMaster', $parameters, '\Asinius\APIClient\SalesPad\Item', $items['Items']);
    }


    /**
     * Retrieve a specific item from the ItemMaster endpoint. Returns null if
     * the item was not found, otherwise returns an Item object.
     *
     * @param   string      $item_number
     *
     * @throws  RuntimeException
     *
     * @return  Item|null
     */
    public static function get (string $item_number)
    {
        $items = static::search("Item_Number eq '$item_number'");
        if ( $items->count() < 1 ) {
            return null;
        }
        if ( $items->count() > 1 ) {
            throw new RuntimeException(sprintf('Multiple results were returned by ItemMaster for Item_Number %s', Asinius::to_str($item_number)));
        }
        return $items[0];
    }


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


    /**
     * Set a property value in an Item object. This function handles the property
     * name mappings defined in Item::map() and will call a callable if one is
     * defined for the given property.
     *
     * @param   string      $key
     * @param   mixed       $value
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    protected function _set_property (string $key, $value)
    {
        if ( array_key_exists($key, static::$_field_maps) ) {
            if ( is_string(static::$_field_maps[$key]) ) {
                $this->_properties[static::$_field_maps[$key]] = $value;
            }
            else if ( is_callable(static::$_field_maps[$key]) ) {
                $this->_properties = array_merge($this->_properties, static::$_field_maps[$key]($value));
            }
            else {
                throw new RuntimeException("Unsupported field map: $key");
            }
        }
        else {
            $this->_properties[$key] = $value;
        }
    }


    /**
     * Instantiate a new Item object using the properties described in $item_detail.
     * At this time, no sanity-checking is done on $item_detail.
     *
     * This library is structured so that application code should never need to
     * instantiate an Item object directly. For that reason, this function will
     * throw() an exception if it isn't being called by the Iterator class.
     *
     * @param   array       $item_detail
     */
    public function __construct (array $item_detail)
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Iterator');
        foreach ($item_detail as $key => $value) {
            if ( is_string($value) ) {
                //  SalesPad returns a lot of right-padded spacing.
                $value = trim($value);
            }
            if ( $key === 'Item_Number' ) {
                $this->_id = $value;
            }
            $this->_set_property($key, $value);
        }
    }


    /**
     * Return a property from a SalesPad Item.
     *
     * @param  string   $property
     *
     * @return mixed
     */
    public function __get (string $property)
    {
        if ( array_key_exists($property, $this->_properties) ) {
            return $this->_properties[$property];
        }
        return null;
    }


    /**
     * Set a property. This bypasses the mappings defined in Item::map() and
     * allows applications to set arbitrary properties and values.
     *
     * @param   string      $key
     * @param   mixed       $value
     *
     * @return  void
     */
    public function __set (string $key, $value)
    {
        $this->_set_property($key, $value);
    }


    /**
     * Return the current Item and its properties in a simple text format.
     * This is mostly useful for debugging.
     *
     * @return  string
     */
    public function __toString (): string
    {
        $out = ['Item ' . $this->_id];
        $keylen = max(array_map('strlen', array_keys($this->_properties)));
        foreach ($this->_properties as $key => $value) {
            $out[] = sprintf("%{$keylen}s: %s", $key, Asinius::to_str($value));
        }
        return implode("\n", $out);
    }

}
