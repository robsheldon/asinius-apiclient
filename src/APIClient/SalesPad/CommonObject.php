<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\CommonObject                                    *
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
use Asinius\APIClient\SalesPad\Iterator;

/**
 * \Asinius\APIClient\SalesPad\CommonObject
 *
 * A lot of SalesPad "entities" (Items, Inventory, SalesDocuments, Customers)
 * share common behaviors, so a simple shared base class gives us a lot of
 * endpoint support for cheap.
 *
 * @method static   mixed   create(array $values)
 */
class CommonObject
{

    protected static    $_endpoint      = '';
    protected static    $_id_key        = '';
    protected static    $_short_name    = '';
    protected static    $_field_maps    = [];
    protected static    $_maps_xref     = [];
    protected           $_id            = '';
    protected           $_properties    = [];


    /**
     * Provide support for a create() static method that can't be implemented
     * in the normal way due to a limitation in PHP.
     *
     * @param   string      $function
     * @param   array       $arguments
     *
     * @throws  InvalidArgumentException|RuntimeException|BadMethodCallException
     *
     * @return  mixed
     */
    public static function __callStatic (string $function, array $arguments)
    {
        switch ($function) {
            case 'create':
                //  This is here because derived classes need to enforce
                //  constraints on the arguments they'll accept when creating
                //  new records in SalesPad, but PHP doesn't allow method
                //  overloading with mismatched function signatures, so I can't
                //  define CommonObject::create() in the usual way.
                if ( count($arguments) !== 1 ) {
                    throw new InvalidArgumentException(sprintf('%s::%s(): wrong number of arguments (expecting 1, got %s', static::class, $function, count($arguments)));
                }
                $values = array_shift($arguments);
                if ( ! is_array($values) ) {
                    throw new InvalidArgumentException(sprintf('%s::$s() expects argument 1 to be an array', static::class, $function));
                }
                $results = SalesPad::call(static::$_endpoint, 'POST', $values);
                //  SalesPad -typically- returns the newly-created record.
                if ( isset($results[static::$_id_key]) ) {
                    //  Passing null for the endpoint prevents the Iterator from trying to repeat
                    //  this function call.
                    $entry = new Iterator(null, $values, static::class, [$results]);
                    return $entry[0];
                }
                throw new RuntimeException(sprintf('Unexpected response to POST from server for %s', static::$_endpoint));
            default:
                throw new BadMethodCallException(sprintf('%s::%s() is not defined', static::class, $function));
        }
    }


    /**
     * Configure automatic translation of named properties in entries returned
     * by the SalesPad API.
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
     * Retrieve a collection of entries from the database, with an optional
     * search query. The search query needs to be an OData query string. See also
     * the OData section at https://portal.salespad.net/webapi/GettingStarted.
     *
     * @param   string      $query
     *
     * @throws  RuntimeException
     *
     * @return  Iterator
     */
    public static function search (string $query = ''): Iterator
    {
        if ( static::$_endpoint === '' ) {
            throw new RuntimeException(sprintf('%s::search() is not implemented', static::class));
        }
        $parameters = ['$top' => sprintf('%d', SalesPad::get_page_size())];
        if ( $query !== '' ) {
            $parameters['$filter'] = $query;
        }
        $results = SalesPad::call(static::$_endpoint, 'GET', $parameters);
        if ( ! array_key_exists('Items', $results) ) {
            throw new RuntimeException(sprintf('Unexpected response from server for %s', static::$_endpoint));
        }
        return new Iterator(static::$_endpoint, $parameters, static::class, $results['Items']);
    }


    /**
     * Retrieve a specific entry from this object's endpoint. Returns null if
     * no matching entry was found, otherwise returns an instantiated object..
     *
     * @param   string      $id
     *
     * @throws  RuntimeException
     *
     * @return  mixed
     */
    public static function get (string $id)
    {
        if ( static::$_id_key === '' ) {
            throw new RuntimeException(sprintf('%s::get() is not implemented', static::class));
        }
        $results = static::search(sprintf("%s eq '%s'", static::$_id_key, $id));
        if ( $results->count() < 1 ) {
            return null;
        }
        if ( $results->count() > 1 ) {
            if ( ($short_name = static::$_short_name) === '' ) {
                $short_name = current(array_slice(explode('\\', static::class), -1));
            }
            throw new RuntimeException(sprintf('Multiple results were returned by %s for %s %s', static::$_endpoint, $short_name, Asinius::to_str($id)));
        }
        return $results[0];
    }


    /**
     * Create a new entry in the SalesPad database. Derived classes should overload
     * this function and then call back to it with all of the properties required
     * to add their entry to SalesPad.
     *
     * This function cannot be defined here because derived classes need their own
     * function signature and PHP does not support method overloading. A stub
     * implementation is handled in __callStatic.
     */
    //  public static function create (array $properties) {...}


    /**
     * Set a property value in this object. This function handles the property
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
        $unmapped_key   = $key;
        $unmapped_value = $value;
        if ( array_key_exists($key, static::$_field_maps) ) {
            if ( is_string(static::$_field_maps[$key]) ) {
                $key = static::$_field_maps[$key];
            }
            else if ( is_callable(static::$_field_maps[$key]) ) {
                $mapped = static::$_field_maps[$key]($value);
                $value = reset($mapped);
                $key = key($mapped);
            }
            else {
                throw new RuntimeException("Unsupported field map: $key");
            }
        }
        $this->_properties[$key] = $value;
        //  A cross-reference of mapped keys and value types is maintained here
        //  for use by the unmapped() function and inter-object data sharing.
        if ( ! array_key_exists($unmapped_key, static::$_maps_xref) ) {
            static::$_maps_xref[$unmapped_key] = ['property' => $key, 'api_type' => gettype($unmapped_value)];
        }
    }


    /**
     * Instantiate a new object using $properties.
     *
     * This library is structured so that application code should never need to
     * instantiate an object directly. For that reason, this function will throw()
     * an exception if it isn't being called by the Iterator class.
     *
     * @param   array       $properties
     */
    public function __construct (array $properties)
    {
        Asinius::assert_parent('Asinius\APIClient\SalesPad\Iterator');
        //  Merge user fields into properties before parsing them.
        //  This is done for convenience, but these fields cannot be used for
        //  OData queries.
        if ( array_key_exists('UserFieldNames', $properties) && array_key_exists('UserFieldData', $properties) ) {
            $properties = array_merge($properties, array_combine($properties['UserFieldNames'], $properties['UserFieldData']));
            unset($properties['UserFieldNames']);
            unset($properties['UserFieldData']);
        }
        foreach ($properties as $key => $value) {
            if ( is_string($value) ) {
                //  SalesPad returns a lot of right-padded spacing.
                $value = trim($value);
            }
            if ( $key === static::$_id_key ) {
                $this->_id = $value;
            }
            $this->_set_property($key, $value);
        }
    }


    /**
     * Return a property from this entry.
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
     * Set a property. This bypasses the mappings defined in static::map() and
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
     * Return the current value of a property referenced by its unmapped
     * (original) property name. This is so that objects in the API can reliably
     * get information from other objects even if the application has remapped
     * some property names for convenience.
     *
     * The value is returned in the same type as it was received from the API.
     *
     * @param   string      $property
     *
     * @return  mixed
     */
    public function unmapped (string $property)
    {
        if ( array_key_exists($property, static::$_maps_xref) ) {
            $key = static::$_maps_xref[$property]['property'];
            if ( array_key_exists($key, $this->_properties) ) {
                if ( $this->_properties[$key] === null ) {
                    return null;
                }
                switch (static::$_maps_xref[$property]['api_type']) {
                    case 'double':
                        return floatval($this->_properties[$key]);
                    case 'integer':
                        return intval($this->_properties[$key]);
                    case 'boolean':
                        if ( is_string($this->_properties[$key]) ) {
                            return (! empty($this->_properties[$key]) && strtolower($this->_properties[$key] !== 'false'));
                        }
                        return $this->_properties[$key] ? true : false;
                    case 'string':
                        return sprintf('%s', $this->_properties[$key]);
                    default:
                        return $this->_properties[$key];
                }
            }
        }
        return null;
    }


    /**
     * Return the current object and its properties in a simple text format.
     * This is mostly useful for debugging.
     *
     * @return  string
     */
    public function __toString (): string
    {
        if ( ($short_name = static::$_short_name) === '' ) {
            $short_name = current(array_slice(explode('\\', static::class), -1));
        }
        $out = [sprintf('%s %s', $short_name, $this->_id)];
        //  To make the output really pretty, calculate the left-side spacing
        //  required to make everything line up.
        $keylen = max(array_map('strlen', array_keys($this->_properties)));
        //  Compile the output and print it. Asinius::to_str() is a utility
        //  function that does a great job of printing assorted values.
        //  This is done this way to gracefully prevent a trailing newline,
        //  since an application may want to embed this output in something else.
        foreach ($this->_properties as $key => $value) {
            $out[] = sprintf("%{$keylen}s: %s", $key, Asinius::to_str($value));
        }
        return implode("\n", $out);
    }

}
