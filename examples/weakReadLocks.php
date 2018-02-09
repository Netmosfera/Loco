<?php

use Netmosfera\Loco\Loco;
use Netmosfera\Loco\LocoError;
use function PHPToolBucket\Bucket\callerClassScope;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

require(__DIR__ . "/../vendor/autoload.php");

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

function locoRead(Closure $code){
    $RF = new ReflectionFunction($code);
    $object = $RF->getClosureThis();
    assert(is_object($object));
    $allowedScopeRC = $RF->getClosureScopeClass();
    assert($allowedScopeRC !== NULL);
    $callingScope = callerClassScope(1);
    if($callingScope === $allowedScopeRC->getName()){
        return $code();
    }else{
        return (new Loco())->readTransaction($code, $object);
    }
}

function locoWrite(Closure $code){
    $RF = new ReflectionFunction($code);
    $object = $RF->getClosureThis();
    assert(is_object($object));
    $allowedScopeRC = $RF->getClosureScopeClass();
    assert($allowedScopeRC !== NULL);
    $callingScope = callerClassScope(1);
    if($callingScope === $allowedScopeRC->getName()){
        return $code();
    }else{
        return (new Loco())->writeTransaction($code, $object);
    }
}

function locoGetStateID($object){
    return (new Loco())->getStateID($object);
}

function locoVerifyStateID($object, $stateID){
    (new Loco())->verifyStateID($object, $stateID);
}

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

class LocoList implements IteratorAggregate
{
    private $storage;

    function __construct(Array $data = []){
        $this->storage = array_values($data);
    }

    function add(Int $offset, $value){
        return locoWrite(function() use($offset, $value){
            assert($offset >= 0 && $offset <= $this->count());
            array_splice($this->storage, $offset, 0, [$value]);
        });
    }

    function set(Int $offset, $value){
        return locoWrite(function() use($offset, $value){
            assert($offset >= 0 && $offset <= $this->count());
            $this->storage[$offset] = $value;
        });
    }

    function count(){
        return locoRead(function(){
            return count($this->storage);
        });
    }

    function get(Int $offset){
        return locoRead(function() use($offset){
            return $this->storage[$offset] ?? NULL;
        });
    }

    function getIterator(): Iterator{
        return new LocoListIterator($this);
    }
}

class LocoListIterator implements Iterator
{
    private $offset = 0;

    private $list;

    private $expectedStateID;

    function __construct(LocoList $list){
        $this->list = $list;
        $this->rewind();
    }

    function rewind(){
        $this->expectedStateID = locoGetStateID($this->list);
        $this->offset = 0;
    }

    function next(){
        locoVerifyStateID($this->list, $this->expectedStateID);
        assert($this->valid(), new Error());
        $this->offset++;
    }

    function valid(){
        locoVerifyStateID($this->list, $this->expectedStateID);
        return $this->offset < $this->list->count();
    }

    function key(){
        locoVerifyStateID($this->list, $this->expectedStateID);
        assert($this->valid(), new Error());
        return $this->offset;
    }

    function current(){
        locoVerifyStateID($this->list, $this->expectedStateID);
        assert($this->valid(), new Error());
        return $this->list->get($this->offset);
    }
}

$list = new LocoList(["a", "b", "c", "d"]);

$iterator = $list->getIterator();
assert($iterator->key() === 0);
assert($iterator->current() === "a");

$iterator->next();

assert($iterator->key() === 1);
assert($iterator->current() === "b");

$iterator->next();

// Concurrent modification invalidates the iterator, as it is
// impossible to determine where the iterator must resume:
$list->add(0, "x");
$list->add(0, "y");
$list->add(0, "z");

try{ $iterator->key(); }
catch(LocoError $locoError){}
assert(isset($locoError));

// But after it is rewound, it works again:

$iterator->rewind();

assert($iterator->key() === 0);
assert($iterator->current() === "z");

$iterator->next();

assert($iterator->key() === 1);
assert($iterator->current() === "y");

$iterator->next();

assert($iterator->key() === 2);
assert($iterator->current() === "x");

$iterator->next();

assert($iterator->key() === 3);
assert($iterator->current() === "a");

echo "end";
