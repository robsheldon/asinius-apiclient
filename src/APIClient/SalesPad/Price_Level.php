<?php


/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\Price_Level                                     *
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

use RuntimeException, InvalidArgumentException, BadMethodCallException;
use Asinius\Asinius;
use Asinius\APIClient\SalesPad;

/**
 * \Asinius\APIClient\SalesPad\Price_Level
 *
 * A wrapper for SalesPad "Price Levels". This is currently used by SalesDocument
 * to validate the price level parameter when creating a new order.
 *
 * @property-read   string      $name
 * @property-read   string      $description
 */
class Price_Level
{

    protected static    $_levels        = [];
    protected           $_name          = '';
    protected           $_description   = '';


    /**
     * Load available price levels into the cache and return a matching price
     * level object.
     *
     * Price levels are only cached once (they aren't expected to change during
     * an API session). Price level IDs (names) are case-insensitive when being
     * retrieved from the cache.
     *
     * If $level_id is blank, then an array of all available price levels will
     * be returned.
     *
     * @param   string      $level_id
     *
     * @return  mixed
     */
    public static function get (string $level_id = '')
    {
        if ( count(static::$_levels) == 0 ) {
            $results = SalesPad::call('/api/PriceLevel', 'GET', []);
            if ( ! array_key_exists('Items', $results) ) {
                throw new RuntimeException('Unexpected response from server for /api/PriceLevel');
            }
            $levels = $results['Items'];
            static::$_levels = array_combine(
                array_map(function($name){
                    return strtolower(trim($name));
                }, array_column($levels, 'Price_Level')),
                array_map(function($properties){
                    return [
                        'name'          => trim($properties['Price_Level']),
                        'description'   => trim($properties['Description']),
                    ];
                }, $levels)
            );
        }
        if ( ($level_id = strtolower($level_id)) !== '' ) {
            if ( array_key_exists($level_id, static::$_levels) ) {
                return new Price_Level(static::$_levels[$level_id]['name'], static::$_levels[$level_id]['description']);
            }
            return null;
        }
        $levels = array_values(array_map(function($level){
            return new Price_Level($level['name'], $level['description']);
        }, static::$_levels));
        return $levels;
    }


    /**
     * Instantiate a new Price_Level object. Intended to only be called by
     * Price_Level::get().
     *
     * @param   string      $name
     * @param   string      $description
     */
    public function __construct ($name, $description)
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Price_Level');
        $this->_name        = $name;
        $this->_description = $description;
    }


    /**
     * Return the read-only 'name' or 'description' properties.
     *
     * @param   string      $property
     *
     * @return  string
     */
    public function __get ($property) : string
    {
        switch ($property) {
            case 'name':
                return $this->_name;
            case 'description':
                return $this->_description;
            default:
                return null;
        }
    }
}
