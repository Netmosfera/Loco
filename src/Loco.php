<?php declare(strict_types = 1); // atom

namespace Netmosfera\Loco;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use Closure;
use Throwable;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

/**
 * @TODOC
 */
class Loco
{
    protected function isLockedForRead($object){
        return ($object->{"Netmosfera\\Loco\\readLock"} ?? 0) > 0;
    }

    protected function isLockedForWrite($object){
        return isset($object->{"Netmosfera\\Loco\\writeLock"});
    }

    protected function lockForRead($object){
        $object->{"Netmosfera\\Loco\\readLock"} = $object->{"Netmosfera\\Loco\\readLock"} ?? 0;
        $object->{"Netmosfera\\Loco\\readLock"}++;
    }

    protected function unlockForRead($object){
        assert(($object->{"Netmosfera\\Loco\\readLock"} ?? 0) > 0);
        if($object->{"Netmosfera\\Loco\\readLock"} === 1){
            unset($object->{"Netmosfera\\Loco\\readLock"});
        }else{
            $object->{"Netmosfera\\Loco\\readLock"}--;
        }
    }

    protected function lockForWrite($object){
        $object->{"Netmosfera\\Loco\\writeLock"} = TRUE;
    }

    protected function unlockForWrite($object){
        assert(($object->{"Netmosfera\\Loco\\writeLock"} ?? FALSE) === TRUE);
        unset($object->{"Netmosfera\\Loco\\writeLock"});
    }

    function readTransaction(Closure $code, $object){
        if($this->isLockedForWrite($object)){
            throw new LocoError($object, FALSE);
        }
        $this->lockForRead($object);
        $return = $throwable = NULL;
        try{ $return = $code(); }
        catch(Throwable $throwable){}
        $this->unlockForRead($object);
        if($throwable !== NULL){ throw $throwable; }
        else{ return $return; }
    }

    function writeTransaction(Closure $code, $object){
        if($this->isLockedForWrite($object) || $this->isLockedForRead($object)){
            throw new LocoError($object, TRUE);
        }
        $this->lockForWrite($object);
        $return = $throwable = NULL;
        try{ $return = $code(); }
        catch(Throwable $throwable){}
        $this->unlockForWrite($object);
        if($throwable !== NULL){ throw $throwable; }
        else{ return $return; }
    }
}
