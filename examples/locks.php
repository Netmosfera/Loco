<?php

use Netmosfera\Loco\Loco;
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

class Calculator
{
    private $total = 0;

    function getTotal(){
        return locoRead(function(){
            return $this->total;
        });
    }

    function sum(iterable $numbers){
        return locoWrite(function() use($numbers){
            foreach($numbers as $number){
                $this->total += $number;
            }
        });
    }
}

$calculator = new Calculator();

$calculator->sum([42]);

$calculator->sum((function() use($calculator){
    /** @var Calculator $calculator */

    // Third-party doing nasty things

    // Dirty read will immediately error:
    yield $calculator->getTotal();

    // Concurrent modification will immediately error:
    $calculator->sum([20]);
})());
