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

use Asinius\Asinius, Asinius\APIClient\SalesPad, Exception, RuntimeException, OutOfBoundsException;

/*******************************************************************************
*                                                                              *
*   \Asinius\APIClient\SalesPad\Iterator                                       *
*                                                                              *
*******************************************************************************/

class Iterator implements \ArrayAccess, \Countable, \SeekableIterator
{

    protected $_received    = 0;
    protected $_endpoint    = '';
    protected $_parameters  = [];
    protected $_call        = '';
    protected $_call_type   = '';
    protected $_elements    = [];


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


    public function __construct ($endpoint, array $parameters, $class_or_callable, array $elements)
    {
        $this->_received    = 0;
        $this->_endpoint    = $endpoint;
        $this->_parameters  = $parameters;
        $this->_call        = $class_or_callable;
        if ( is_string($class_or_callable) && class_exists($class_or_callable, true) ) {
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


    public function offsetExists (mixed $key)
    {
        return array_key_exists($key, $this->_elements);
    }


    public function &offsetGet (mixed $key)
    {
        return $this->_elements[$key];
    }


    public function offsetSet ($key, $value)
    {
        $this->_elements[$key] = $value;
    }


    public function offsetUnset ($key)
    {
        unset($this->_elements[$key]);
    }


    /**
     * Return the current number of elements stored in the iterator
     * (before the next page is loaded).
     *
     * @return  int
     */
    public function count ()
    {
        //  IMPORTANT: This will return the CURRENT count of elements stored in
        //  the iterator; it's not worth loading all available pages for a query
        //  just to return a count value, and the SalesPad API doesn't offer a
        //  way to get a count of results for a query.
        return count($this->_elements);
    }


    public function current ()
    {
        //  Trigger the next page load if necessary.
        $this->key();
        return current($this->_elements);
    }


    public function key ()
    {
        if ( key($this->_elements) === null ) {
            $this->_load_next_page();
        }
        return key($this->_elements);
    }


    public function next ()
    {
        //  Trigger the next page load if necessary.
        $this->key();
        return next($this->_elements);
    }


    public function prev ()
    {
        return prev($this->_elements);
    }


    public function rewind ()
    {
        reset($this->_elements);
    }


    public function valid ()
    {
        //  Trigger the next page load if necessary.
        return $this->key() !== null;
    }


    public function seek (int $index)
    {
        if ( ! $this->offsetExists($index) ) {
            throw new OutOfBoundsException("Can't seek() to index $index");
        }
        //  Lame, but it works and doesn't require the overhead of manually
        //  keeping track of an index for a simple ordered list.
        //  Internally, PHP's native ArrayIterator::seek() function also needs
        //  to traverse the list until it finds a matching index.
        while ( $index > $this->key() ) {
            $this->next();
        }
        while ( $index < $this->key() ) {
            $this->prev();
        }
    }


    public function end ()
    {
        return end($this->_elements);
    }


    public function keys ()
    {
        return keys($this->_elements);
    }


    public function values ()
    {
        return values($this->_elements);
    }


    public function push (...$values)
    {
        array_push($this->_elements, ...$values);
    }


    public function pop ()
    {
        return array_pop($this->_elements);
    }
}
