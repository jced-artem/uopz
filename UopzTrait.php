<?php

namespace Jced;

/**
 * Trait UopzTrait
 *
 * Useful wrapper for uopz library's functions
 */
trait UopzTrait
{
    /**
     * Hook non-return third-party-called function to get return from it
     *
     * Simple example:
     * 
     * public function foo($data) {
     *     return Database::insert($data);
     * }
     * public function bar() {
     *     $data = ['field' => 'value'];
     *     foo($data);
     * }
     *
     * Sometime when you testing bar() you may want to know what happend in foo()
     *
     * public function testBar() {
     *     $this->uopzFunctionHook(
     *         'foo',
     *         function ($data) {
     *             return $data;
     *         },
     *         $fooResult
     *     );
     *     bar();
     *     $this->assertEqual(['field' => 'value'], $fooResult);
     * }
     *
     * @param string|array $function Function/method name
     * @param Closure $closure Closure
     * @param mixed $return Referenced return
     * @param bool $backup Backup
     */
    public function uopzFunctionHook($function, Closure $closure, &$return, $backup = false)
    {
        $this->uopzFunction(
            $function,
            function () use (&$return, $closure) {
                $return = call_user_func_array($closure, func_get_args());
                return $return;
            },
            $backup
        );
    }

    /**
     * Replace function's return value using list of conditions.
     * Source function cant be redefined or defined as closure before using this method.
     * Example:
     * 
     * public function selectAll($table) {
     *     return $db->select()->from($table)->fetchAll();
     * }
     * 
     * public function foo() {
     *     $result1 = $this->selectAll('user');
     *     // do something
     *     $result2 = $this->selectAll('article');
     *     // do something
     *     $result3 = $this->selectAll('post');
     * }
     *
     * You can mock all calls just doing:
     *
     * public function testFoo() {
     *     $this->uopzFunctionConditionReturn(
     *         'selectAll',
     *         [
     *             ['table', 'user', [0 => 'user1', 2 => 'user3']],
     *             ['table', 'post', function () { return 'some other result here'; }],
     *         ],
     *         null // for all other queries
     *     );
     * }
     *
     * @param string|array $function Function/method name
     * @param array $conditionList Conditions
     * @param bool $backup Backup
     * @param mixed $default Default return
     * @throws Exception
     */
    public function uopzFunctionConditionReturn($function, array $conditionList, $default = null, $backup = false)
    {
        if (is_array($function)) {
            $replacement = new ReflectionMethod($function[0], $function[1]);
        } else {
            $replacement = new ReflectionFunction($function);
        }
        if ('{closure}' == $replacement->getName() || $replacement->isClosure()) {
            throw new Exception('Cant apply conditions to closure or replaced function. Try to restore function before.');
        }
        $parameters = array_map(
            function ($parameter) {
                return $parameter->getName();
            },
            $replacement->getParameters()
        );
        $this->uopzFunction(
            $function,
            function () use ($parameters, $conditionList, $default) {
                $returnConvert = function ($return) {
                    if ($return instanceof Closure) {
                        return $return();
                    } elseif (is_object($return)) {
                        return clone $return;
                    } else {
                        return $return;
                    }
                };
                foreach ($conditionList as list($parameterName, $needle, $return)) {
                    $key = in_array($parameterName, $parameters);
                    if (false !== $key && func_get_arg($key) === $needle) {
                        return $returnConvert($return);
                    }
                }
                return $returnConvert($default);
            },
            $backup
        );
    }

    /**
     * Consistent return
     *
     * @param string|array $function Function name
     * @param array $return Return
     * @param bool $backup Backup
     */
    public function uopzFunctionConsistentReturn($function, array $return, $backup = false)
    {
        $this->uopzFunction(
            $function,
            function () use ($return) {
                static $callNumber = 0;
                $return = $return[$callNumber++];
                if ($return instanceof Closure) {
                    return $return();
                } elseif (is_object($return)) {
                    return clone $return;
                } else {
                    return $return;
                }
            },
            $backup
        );
    }
    
    /**
     * Replace one function with another
     *
     * Example:
     * 
     * $this->uopzFunctionReplace(['mysqli', 'query'], 'mysql_query'); // downgrade :)
     * 
     * @param string|array $function Target function name
     * @param string|array $replace Source function name
     * @param bool $backup Backup
     */
    public function uopzFunctionReplace($function, $replace, $backup = false)
    {
        $this->uopzFunction(
            $function,
            function () use ($replace) {
                if (is_array($replace)) {
                    if (is_object($replace[0])) {
                        $className = get_class($replace[0]);
                        $object = $replace[0];
                    } else {
                        $className = $replace[0];
                        $object = null;
                    }
                    $replacement = new ReflectionMethod($className, $replace[1]);
                    return $replacement->invokeArgs($object, func_get_args());
                } else {
                    $replacement = new ReflectionFunction($replace);
                    return $replacement->invokeArgs(func_get_args());
                }
            },
            $backup
        );
    }

    /**
     * Just return something, nothing more
     * 
     * @param string|array $function Function name
     * @param mixed $return Return
     * @param bool $backup Backup
     */
    public function uopzFunctionSimpleReturn($function, $return, $backup = false)
    {
        $this->uopzFunction(
            $function,
            function () use ($return) {
                if ($return instanceof Closure) {
                    return $return();
                } elseif (is_object($return)) {
                    return clone $return;
                } else {
                    return $return;
                }
            },
            $backup
        );
    }

    /**
     * Backup function
     *
     * @param string|array $function Function name
     */
    public function uopzBackup($function)
    {
        $this->uopzInvoke('backup', $function);
    }

    /**
     * @param string|array $function Restore function
     */
    public function uopzRestore($function)
    {
        $this->uopzInvoke('restore', $function);
    }

    /**
     * Ask function to do nothing :)
     *
     * @param string|array $function Function name
     * @param bool $backup Backup
     */
    public function uopzMuteFunction($function, $backup = false)
    {
        $this->uopzFunction(
            $function,
            function () {
                return ;
            },
            $backup
        );
    }

    /**
     * Check docs for uops_function ;)
     *
     * @param string|array $function
     * @param Closure $closure
     * @param bool $backup
     */
    public function uopzFunction($function, Closure $closure, $backup = false)
    {
        if ($backup) {
            $this->uopzBackup($function);
        }
        $this->uopzInvoke('function', $function, [$closure]);
    }

    /**
     * Check docs for uops_redefine ;)
     *
     * @param string|array $constant
     * @param mixed $value
     */
    public function uopzRedefine($constant, $value)
    {
        $this->uopzInvoke('redefine', $constant, [$value]);
    }

    /**
     * Check docs for uops_flags ;)
     *
     * My favourite useful example:
     *
     * public function testMyPrivateMethod() {
     *     // make private method public
     *     $this->uopzFlags(['MyClass', 'myPrivateMethod'], ZEND_ACC_PUBLIC);
     *     $myClassObject = new MyClass();
     *     $this->assertTrue($myClassObject->myPrivateMethod());
     * }
     *
     * @param string|array $function
     * @param int $flags
     */
    public function uopzFlags($function, $flags)
    {
        $this->uopzInvoke('flags', $function, [$flags]);
    }

    /**
     * Call native functions, never mind
     *
     * @param string $name
     * @param string|array $functionName
     * @param array $params
     */
    private function uopzInvoke($name, $functionName, array $params = [])
    {
        $replacement = new ReflectionFunction('uopz_' . $name);
        if (!is_array($functionName)) {
            $functionName = [$functionName];
        }
        $replacement->invokeArgs(array_merge($functionName, $params));
    }
}
