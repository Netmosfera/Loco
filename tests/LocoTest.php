<?php declare(strict_types = 1); // atom

namespace Netmosfera\LocoTests;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use function PHPToolBucket\Bucket\callerClassScope;
use PHPUnit\Framework\TestCase;
use Netmosfera\Loco\LocoError;
use Netmosfera\Loco\Loco;
use ReflectionFunction;
use Closure;

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

class test_read_lock_prevents_inheritors_from_acquiring_write_locks{
    function read (){ return locoRead (function(){ return $this->other->writeObjectConcurrently(); }); }
    function write(){ return locoWrite(function(){ }); }
}

class test_read_lock_does_not_prevent_inheritors_from_acquiring_read_locks{
    function read1(){ return locoRead (function(){ return $this->other->readObjectConcurrently(); }); }
    function read2(){ return locoRead (function(){ return 42; }); }
}

class test_write_lock_prevents_inheritors_from_acquiring_write_locks{
    function write1(){ return locoWrite(function(){ return $this->other->writeObjectConcurrently(); }); }
    function write2(){ return locoWrite(function(){ }); }
}

class test_write_lock_prevents_inheritors_from_acquiring_read_locks{
    function write(){ return locoWrite(function(){ return $this->other->readObjectConcurrently(); }); }
    function read (){ return locoRead (function(){ }); }
}

class LocoTest extends TestCase
{
    function test_read_lock_prevents_inheritors_from_acquiring_write_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new test_read_lock_prevents_inheritors_from_acquiring_write_locks();
        $other = new class() extends test_read_lock_prevents_inheritors_from_acquiring_write_locks{
            function writeObjectConcurrently(){ $this->object->write(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->read();
    }

    function test_read_lock_does_not_prevent_inheritors_from_acquiring_read_locks(){
        $object = new test_read_lock_does_not_prevent_inheritors_from_acquiring_read_locks();
        $other = new class() extends test_read_lock_does_not_prevent_inheritors_from_acquiring_read_locks{
            function readObjectConcurrently(){ return $this->object->read2(); }
        };
        $other->object = $object;
        $object->other = $other;
        self::assertSame(42, $object->read1());
    }

    function test_write_lock_prevents_inheritors_from_acquiring_write_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new test_write_lock_prevents_inheritors_from_acquiring_write_locks();
        $other = new class() extends test_write_lock_prevents_inheritors_from_acquiring_write_locks{
            function writeObjectConcurrently(){ $this->object->write2(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->write1();
    }

    function test_write_lock_prevents_inheritors_from_acquiring_read_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new test_write_lock_prevents_inheritors_from_acquiring_read_locks();
        $other = new class() extends test_write_lock_prevents_inheritors_from_acquiring_read_locks{
            function readObjectConcurrently(){ $this->object->read(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->write();
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_read_lock_prevents_third_parties_from_acquiring_write_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new class(){
            function read (){ return locoRead (function(){ return $this->other->writeObjectConcurrently(); }); }
            function write(){ return locoWrite(function(){ }); }
        };
        $other = new class(){
            function writeObjectConcurrently(){ $this->object->write(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->read();
    }

    function test_read_lock_does_not_prevent_third_parties_from_acquiring_read_locks(){
        $object = new class(){
            function read1(){ return locoRead (function(){ return $this->other->readObjectConcurrently(); }); }
            function read2(){ return locoRead (function(){ return 42; }); }
        };
        $other = new class(){
            function readObjectConcurrently(){ return $this->object->read2(); }
        };
        $other->object = $object;
        $object->other = $other;
        self::assertSame(42, $object->read1());
    }

    function test_write_lock_prevents_third_parties_from_acquiring_write_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new class(){
            function write1(){ return locoWrite(function(){ return $this->other->writeObjectConcurrently(); }); }
            function write2(){ return locoWrite(function(){ }); }
        };
        $other = new class(){
            function writeObjectConcurrently(){ $this->object->write2(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->write1();
    }

    function test_write_lock_prevents_third_parties_from_acquiring_read_locks(){
        $this->expectException(LocoError::CLASS);
        $object = new class(){
            function write(){ return locoWrite(function(){ return $this->other->readObjectConcurrently(); }); }
            function read (){ return locoRead (function(){ }); }
        };
        $other = new class(){
            function readObjectConcurrently(){ $this->object->read(); }
        };
        $other->object = $object;
        $object->other = $other;
        $object->write();
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_read_lock_does_not_prevent_self_from_accessing_any_sibling_member(){
        $object = new class(){
            function read1 (){ return locoRead (function(){ return $this->read2(); }); }
            function read2 (){ return locoRead (function(){ return $this->write1(); }); }
            function write1(){ return locoWrite(function(){ return $this->write2(); }); }
            function write2(){ return locoWrite(function(){ return 42; }); }
        };
        self::assertSame(42, $object->read1());
    }

    function test_write_lock_does_not_prevent_self_from_accessing_any_sibling_member(){
        $object = new class(){
            function write1(){ return locoWrite(function(){ return $this->write2(); }); }
            function write2(){ return locoWrite(function(){ return $this->read1(); }); }
            function read1 (){ return locoRead (function(){ return $this->read2(); }); }
            function read2 (){ return locoRead (function(){ return 42; }); }
        };
        self::assertSame(42, $object->write1());
    }
}
