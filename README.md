# uopz
Wrapper for php uopz library's functions. Very useful for unit testing for changing function's behavior in runtime.

### Requirements
```
oupz library
```
You can install it:
- for `php-7`: `pecl install uopz`
- for `php-5.6`: `pecl install uopz-2.0.7`

### Install
```
composer require jced-artem/uopz
```
### Usage
```
class yourClass
{
    use Jced\UopzTrait;
    // ...
}
```

### Hook non-return third-party-called function to get return from it
```
public function foo($data) {
    return Database::insert($data);
}
public function bar() {
    $data = ['field' => 'value'];
    foo($data);
}
```
Sometime when you testing bar() you may want to know what happend in foo()
```
public function testBar() {
    $this->uopzFunctionHook(
        'foo',
        function ($data) {
            return $data;
        },
        $fooResult
    );
    bar();
    $this->assertEqual(['field' => 'value'], $fooResult);
}
```
### Replace function's return value using list of conditions.
Source function cant be redefined or defined as closure before using this method.
```
public function selectAll($table) {
    return $db->select()->from($table)->fetchAll();
}
public function foo() {
    $result1 = $this->selectAll('user');
    // do something
    $result2 = $this->selectAll('article');
    // do something
    $result3 = $this->selectAll('post');
}
```
You can mock all calls just doing:
```
public function testFoo() {
    $this->uopzFunctionConditionReturn(
        'selectAll',
        [
            ['table', 'user', [0 => 'user1', 2 => 'user3']],
            ['table', 'post', function () { return 'some other result here'; }],
        ],
        null // for all other queries
    );
}
```
### Consistent return
```
public function testFoo() {
    $this->uopzFunctionConditionReturn(
        'selectAll',
        [
            [0 => 'user1', 2 => 'user3'],
            'some other result here',
        ]
    );
}
```
### Replace one function with another
```
$this->uopzFunctionReplace(['mysqli', 'query'], 'mysql_query'); // downgrade :)
```
### Simple return
Just return something, nothing more
```
$this->uopzFunctionSimpleReturn('functionName', 'return string');
```
### Backup function
```
$this->uopzBackup('functionName');
```
### Restore function
```
$this->uopzRestore('functionName');
```
### Mute function
Ask function to do nothing :)
```
$this->uopzMuteFunction('functionName');
```
### Aliases
```
// uops_function
    public function uopzFunction($function, Closure $closure, $backup = false);
// uops_redefine
    public function uopzRedefine($constant, $value);
// uops_flags
    public function uopzFlags($function, $flags);
```
