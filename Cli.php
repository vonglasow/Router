<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2013, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Router\Exception
 */
-> import('Router.Exception.~')

/**
 * \Hoa\Router\Exception\NotFound
 */
-> import('Router.Exception.NotFound')

/**
 * \Hoa\Router
 */
-> import('Router.~')

/**
 * \Hoa\Router\Generic
 */
-> import('Router.Generic');

}

namespace Hoa\Router {

/**
 * Class \Hoa\Router\Cli.
 *
 * CLI router.
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2013 Ivan Enderlin.
 * @license    New BSD License
 */

class Cli extends Generic implements \Hoa\Core\Parameter\Parameterizable {

    /**
     * Parameters.
     *
     * @var \Hoa\Core\Parameter object
     */
    protected $_parameters    = null;

    /**
     * CLI methods that the router understand.
     *
     * @var \Hoa\Router\Http array
     */
    protected static $_methods = array(
        'get', // classic call
        'post' // pipe/stdin
    );



    /**
     * Constructor.
     *
     * @access  public
     * @return  void
     */
    public function __construct ( Array $parameters = array() ) {

        $this->_parameters = new \Hoa\Core\Parameter(
            $this,
            array(),
            array(
                'base'          => null,
                'rules.public'  => array(),
                'rules.private' => array()
            )
        );
        $this->_parameters->setParameters($parameters);

        foreach($this->_parameters->getParameter('rules.public') as $id => $rule) {

            @list($methods, $pattern, $call, $able, $variables)
                = $rule;

            if(null === $variables)
                $variables = array();

            $this->addRule($id, $methods, $pattern, $call, $able, $variables);
        }

        foreach($this->_parameters->getParameter('rules.private') as $id => $rule) {

            @list($methods, $pattern, $call, $able, $variables)
                = $rule;

            if(null === $variables)
                $variables = array();

            $this->addPrivateRule(
                $id, $methods, $pattern, $call, $able, $variables
            );
        }

        return;
    }

    /**
     * Get parameters.
     *
     * @access  public
     * @return  \Hoa\Core\Parameter
     */
    public function getParameters ( ) {

        return $this->_parameters;
    }

    /**
     * Fallback for add*Rule() methods.
     *
     * @access  public
     * @param   int     $visibility    Visibility (please, see
     *                                 Router::VISIBILITY_* constants).
     * @param   string  $id            ID.
     * @param   array   $methods       HTTP methods allowed by the rule.
     * @param   string  $pattern       Pattern (on-subdomain@on-request).
     * @param   mixed   $call          Call (first part).
     * @param   mixed   $able          Able (second part).
     * @param   array   $variables     Variables (default or additional values).
     * @return  \Hoa\Router\Http
     * @throw   \Hoa\Router\Exception
     */
    protected function _addRule ( $visibility, $id, Array $methods, $pattern,
                                  $call, $able, Array $variables ) {

        if(true === $this->ruleExists($id))
            throw new Exception(
                'Cannot add rule %s because it already exists.', 0, $id);

        array_walk($methods, function ( &$method ) {

            $method = strtolower($method);
        });
        $diff = array_diff($methods, self::$_methods);

        if(!empty($diff))
            throw new Exception(
                (1 == count($diff)
                    ? 'Method %s is'
                    : 'Methods %s are') .
                ' invalid for the rule %s (valid methods are: %s).',
                1, array(implode(', ', $diff), $id,
                         implode(', ', self::$_methods)));

        $this->_rules[$id] = array(
            Router::RULE_VISIBILITY => $visibility,
            Router::RULE_ID         => $id,
            Router::RULE_METHODS    => $methods,
            Router::RULE_PATTERN    => $pattern,
            Router::RULE_CALL       => $call,
            Router::RULE_ABLE       => $able,
            Router::RULE_VARIABLES  => $variables
        );

        return $this;
    }

    /**
     * Find the appropriated rule.
     *
     * @access  public
     * @param   string  $uri     URI or complete URL (without scheme). If null,
     *                           it will be deduce.
     * @param   string  $base    Base. If null, it will be deduce.
     * @return  \Hoa\Router\Cli
     * @throw   \Hoa\Router\Exception\NotFound
     */
    public function route ( $uri = null, $base = null ) {

        if(null === $uri)
            $uri = $this->getURI();

        $method = $this->getMethod();
        $rules  = array_filter(
            $this->getRules(),
            function ( $rule ) use ( &$method ) {

                if(Router::VISIBILITY_PUBLIC != $rule[Router::RULE_VISIBILITY])
                    return false;

                if(false === in_array($method, $rule[Router::RULE_METHODS]))
                    return false;

                return true;
            }
        );

        $gotcha = false;

        foreach($rules as $rule) {

            $pattern = $rule[Router::RULE_PATTERN];

            if(0 !== preg_match('#^' . $pattern . '$#i', $uri, $muri)) {

                $gotcha = true;
                break;
            }
        }

        if(false === $gotcha)
            throw new Exception\NotFound(
                'Cannot found an appropriated rule to route %s.', 5, $uri);

        array_shift($muri);
        $rule[Router::RULE_VARIABLES]['_call'] = &$rule[Router::RULE_CALL];
        $rule[Router::RULE_VARIABLES]['_able'] = &$rule[Router::RULE_ABLE];

        foreach($muri as $key => $value) {

            if(!is_string($key))
                continue;

            $key = strtolower($key);

            if(isset($rule[Router::RULE_VARIABLES][$key]) && empty($value))
                continue;

            $rule[Router::RULE_VARIABLES][$key] = $value;
        }

        $this->_rule = $rule;

        return $this;
    }

    /**
     * Unroute a rule (i.e. route()^-1).
     *
     * @access  public
     * @param   string  $id           ID.
     * @param   array   $variables    Variables.
     * @return  string
     */
    public function unroute ( $id, Array $variables = array() ) {

        $rule      = $this->getRule($id);
        $pattern   = $rule[Router::RULE_PATTERN];
        $variables = array_merge($rule[Router::RULE_VARIABLES], $variables);
        $out       = preg_replace_callback(
            '#\(\?\<([^>]+)>[^\)]*\)[\?\*\+]{0,2}#',
            function ( Array $matches ) use ( &$variables ) {

                $m = strtolower($matches[1]);

                if(!isset($variables[$m]))
                    return '';

                return $variables[$m];
            },
            $pattern
        );

        return str_replace(
            array(
                '\.', '\\\\', '\+', '\*', '\?', '\[', '\]', '\^', '\$', '\(',
                '\)', '\{', '\}', '\=', '\!', '\<', '\>', '\|', '\:', '\-'
            ),
            array(
                '.', '\\', '+', '*', '?', '[', ']', '^', '$', '(',
                ')', '{', '}', '=', '!', '<', '>', '|', ':', '-'
            ),
            $out
        );
    }

    /**
     * Get HTTP method.
     *
     * @access  public
     * @return  string
     */
    public function getMethod ( ) {

        return 'get';
    }

    /**
     * Whether the router is called asynchronously or not.
     *
     * @access  public
     * @return  bool
     */
    public function isAsynchronous ( ) {

        return false;
    }

    /**
     * Get URI.
     *
     * @access  public
     * @return  string
     */
    public function getURI ( ) {

        if(!isset($_SERVER['argv']))
            return null;

        $out   = null;
        $_argv = $_SERVER['argv'];
        array_shift($_argv);

        foreach($_argv as $arg) {

            if(false !== strpos($arg, '=')) {

                if(false !== strpos($arg, '"'))
                    $arg = str_replace(
                        '=',
                        '="',
                        str_replace('"', '\\"', $arg)
                    ) . '"';
                elseif(false !== strpos($arg, '\''))
                    $arg = str_replace(
                        '=',
                        '=\'',
                        str_replace('\'', '\\\'', $arg)
                    ) . '\'';
                elseif(false !== strpos($arg, ' '))
                    $arg = str_replace('=', '="', $arg) . '"';
            }
            elseif(false !== strpos($arg, ' '))
                $arg = '"' . str_replace('"', '\\"', $arg) . '"';
            elseif(false !== strpos($arg, '"'))
                $arg = '"' . str_replace('"', '\\"', $arg) . '"';
            elseif(false !== strpos($arg, '\''))
                $arg = '"' . $arg . '"';

            $out .= ' ' . $arg;
        }

        return ltrim($out);
    }
}

}
