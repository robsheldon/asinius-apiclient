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

use Asinius\Asinius, Asinius\APIClient\SalesPad, RuntimeException;

/**
 * \Asinius\APIClient\SalesPad\Item
 *
 * Encapsulate inventory items in a SalesPad database.
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
     * @param  array    $fields
     *
     * @return void
     */
    public static function map ($fields)
    {
        static::$_field_maps = $fields;
    }


    public static function search (string $query = '')
    {
        $parameters = ['$top' => '100'];
        if ( $query !== '' ) {
            $parameters['$filter'] = $query;
        }
        $items = SalesPad::call('/api/ItemMaster', 'GET', $parameters);
        if ( ! array_key_exists('Items', $items) ) {
            throw new RuntimeException('Unexpected response from server for /api/ItemMaster');
        }
        return new Iterator('/api/ItemMaster', $parameters, '\Asinius\APIClient\SalesPad\Item', $items['Items']);
    }


    public static function get (string $item_number)
    {
        $items = static::search("Item_Number eq '$item_number'");
        if ( $items->count() < 1 ) {
            throw new RuntimeException("Item $item_number was not found");
        }
        return $items[0];
    }


    public static function create (array $values)
    {
        throw new RuntimeException(static::class . '::create() is not implemented');
    }


    protected function _set_property ($key, $value)
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
    public function __get ($property)
    {
        if ( array_key_exists($property, $this->_properties) ) {
            return $this->_properties[$property];
        }
        return null;
    }


    public function __set ($key, $value)
    {
        $this->_set_property($key, $value);
    }


    /**
     * Return the current Item and its properties in a simple text format.
     * This is mostly useful for debugging.
     *
     * @return  string
     */
    public function __toString () : string
    {
        $out = ['Item ' . $this->_id];
        $keylen = max(array_map('strlen', array_keys($this->_properties)));
        foreach ($this->_properties as $key => $value) {
            $out[] = sprintf("%{$keylen}s: %s", $key, Asinius::to_str($value));
        }
        return implode("\n", $out);
    }

}
