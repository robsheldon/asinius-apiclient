<?php

/*******************************************************************************
*                                                                              *
*   Asinius\APIClient\SalesPad\Iterator                                        *
*                                                                              *
*   Iterator class to handle results from the SalesPad API with multiple       *
*   elements, especially when they are paged (spread across multiple API       *
*   requests).                                                                 *
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

use ArrayAccess, Countable, SeekableIterator;
use RuntimeException, OutOfBoundsException;
use Asinius\Asinius;
use Asinius\APIClient\SalesPad;

/**
 * \Asinius\APIClient\SalesPad\Iterator
 *
 * Utility class that provides an iterator that can request additional pages
 * from the SalesPad API's page-offset -based results. Just loop through elements
 * in the Iterator and it will request additional elements from the API as needed.
 */
class Iterator implements ArrayAccess, Countable, SeekableIterator
{

    protected $_received    = 0;
    protected $_endpoint    = '';
    protected $_parameters  = [];
    protected $_call        = '';
    protected $_call_type   = '';
    protected $_elements    = [];


    /**
     * Add a new element to the current Iterator. This will either instantiate
     * whatever class was defined for all elements in the Iterator's constructor,
     * or it will call a callable that was defined instead.
     *
     * @param   array       $elements
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    protected function _push (array $elements)
    {
        switch ($this->_call_type) {
            case 'class':
                foreach ($elements as $element) {
                    $this->_elements[] = new $this->_call($element);
                }
                break;
            case 'callable':
                foreach ($elements as $element) {
                    $this->_elements[] = ($this->_call)($element);
                }
                break;
            default:
                throw new RuntimeException('Something went terribly wrong: $_call_type is not set');
        }
        $this->_received += count($elements);
    }


    /**
     * Retrieve the next page of results from the API request that invoked the
     * Iterator. The request for the next page will either be handled automatically
     * by this function, or this function will call a callable to handle the request
     * instead.
     *
     * @internal
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    protected function _load_next_page ()
    {
        if ( $this->_endpoint === null ) {
            return;
        }
        if ( is_string($this->_endpoint) && preg_match('|^/api/|', $this->_endpoint) === true ) {
            $elements = SalesPad::call($this->_endpoint, 'GET', array_merge($this->_parameters, ['$skip' => $this->_received]));
            $elements = array_intersect_key($elements, ['Items' => true]);
            if ( empty($elements) ) {
                throw new RuntimeException('Unhandled response from SalesPad API when requesting for the next page for ' . $this->_endpoint);
            }
            $elements = array_shift($elements);
        }
        else if ( is_callable($this->_endpoint) ) {
            $elements = ($this->_endpoint)($this->_parameters);
        }
        else {
            throw new RuntimeException(sprintf('Invalid endpoint: %s', Asinius::to_str($this->_endpoint)));
        }
        if ( count($elements) === 0 ) {
            //  No more results returned.
            $this->_endpoint = null;
            return;
        }
        $this->_push($elements);
    }


    /**
     * Create a new Iterator. This is intended to be called by static functions
     * in API-specific classes, but can be called by an application if necessary.
     *
     * $endpoint is either a string describing the API endpoint for the query
     * that's generating the Iterator, or it is a callable that will handle
     * subsequent requests instead (see also the Inventory class).
     *
     * $parameters is an array of whatever request paraemters should be passed
     * to the $endpoint API or callable.
     *
     * $class_or_callable describes what should happen every time a new element
     * is added to the Iterator. It can be a string describing a class, which
     * will instantiate that class, or it can be a callable, which should return
     * the new element.
     *
     * $elements is the initial list of elements to be added to the Iterator.
     * These will be instantiated or handled by $class_or_callable before they
     * are pushed into the Iterator.
     *
     * @param   mixed       $endpoint
     * @param   array       $parameters
     * @param   mixed       $class_or_callable
     * @param   array       $elements
     *
     * @throws  RuntimeException
     */
    public function __construct ($endpoint, array $parameters, $class_or_callable, array $elements)
    {
        $this->_received    = 0;
        $this->_endpoint    = $endpoint;
        $this->_parameters  = $parameters;
        $this->_call        = $class_or_callable;
        if ( is_string($class_or_callable) && class_exists($class_or_callable) ) {
            $this->_call_type = 'class';
        }
        else if ( is_callable($class_or_callable) ) {
            $this->_call_type = 'callable';
        }
        else {
            throw new RuntimeException(sprintf('Not a valid class name or callable type: %s', Asinius::to_str($class_or_callable)));
        }
        $this->_push($elements);
    }


    /**
     * offsetExists() is required for iterator objects.
     *
     * @param   mixed       $offset
     *
     * @return  boolean
     */
    public function offsetExists ($offset): bool
    {
        return array_key_exists($offset, $this->_elements);
    }


    /**
     * offsetGet() is required for iterator objects.
     *
     * @param   mixed       $offset
     *
     * @return  mixed
     */
    public function &offsetGet ($offset)
    {
        return $this->_elements[$offset];
    }


    /**
     * offsetSet() is required for iterator objects.
     *
     * @param   mixed       $offset
     * @param   mixed       $value
     *
     * @return  void
     */
    public function offsetSet ($offset, $value)
    {
        $this->_elements[$offset] = $value;
    }


    /**
     * offsetUnset() is required for iterator objects.
     *
     * @param   mixed       $offset
     *
     * @return  void
     */
    public function offsetUnset ($offset)
    {
        unset($this->_elements[$offset]);
    }


    /**
     * Return the current number of elements currently stored in the iterator
     * (before the next page is loaded).
     *
     * @return  int
     */
    public function count (): int
    {
        //  IMPORTANT: This will return the CURRENT count of elements stored in
        //  the iterator; it's not worth loading all available pages for a query
        //  just to return a count value, and the SalesPad API doesn't offer a
        //  way to get a count of results for a query. (That sure would be nice!)
        return count($this->_elements);
    }


    /**
     * current() is required for iterator objects.
     *
     * @return  mixed
     */
    public function current ()
    {
        //  Trigger the next page load if necessary.
        $this->key();
        return current($this->_elements);
    }


    /**
     * key() is required for iterator objects.
     *
     * @return  int|string|null
     */
    public function key ()
    {
        if ( key($this->_elements) === null ) {
            $this->_load_next_page();
        }
        return key($this->_elements);
    }


    /**
     * next() is required for iterator objects.
     *
     * @return  mixed
     */
    public function next ()
    {
        //  Trigger the next page load if necessary.
        $this->key();
        return next($this->_elements);
    }


    /**
     * prev() is required for iterator objects.
     *
     * @return  mixed
     */
    public function prev ()
    {
        return prev($this->_elements);
    }


    /**
     * rewind() is required for iterator objects.
     *
     * @return  mixed
     */
    public function rewind ()
    {
        return reset($this->_elements);
    }


    /**
     * valid() is required for iterator objects.
     *
     * @return  boolean
     */
    public function valid (): bool
    {
        //  Trigger the next page load if necessary.
        return $this->key() !== null;
    }


    /**
     * seek() is required for iterator objects.
     *
     * @param   mixed       $offset
     *
     * @throws  RuntimeException
     *
     * @return  void
     */
    public function seek ($offset)
    {
        if ( ! $this->offsetExists($offset) ) {
            throw new OutOfBoundsException(sprintf("Can't seek() to offset %s", Asinius::to_str($offset)));
        }
        //  Lame, but it works and doesn't require the overhead of manually
        //  keeping track of an index for a simple ordered list.
        //  Internally, PHP's native ArrayIterator::seek() function also needs
        //  to traverse the list until it finds a matching index.
        while ( $offset > $this->key() ) {
            $this->next();
        }
        while ( $offset < $this->key() ) {
            $this->prev();
        }
    }


    /**
     * end() is required for iterator objects.
     *
     * @return  mixed
     */
    public function end ()
    {
        return end($this->_elements);
    }


    /**
     * Return an array of the keys in the current Iterator.
     *
     * @return  array
     */
    public function keys (): array
    {
        return array_keys($this->_elements);
    }


    /**
     * Return a simple array of the values in the current Iterator.
     *
     * @return  array
     */
    public function values (): array
    {
        return array_values($this->_elements);
    }


    /**
     * Append a new element to the Iterator.
     *
     * @param   mixed       $values
     *
     * @return  void
     */
    public function push (...$values)
    {
        array_push($this->_elements, ...$values);
    }


    /**
     * Remove the last element from the Iterator and return it.
     *
     * @return  mixed
     */
    public function pop ()
    {
        return array_pop($this->_elements);
    }
}
