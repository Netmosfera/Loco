<?php declare(strict_types = 1); // atom

namespace Netmosfera\Loco;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use Closure;
use Throwable;
use const PHP_INT_MIN;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

/**
 * @TODOC
 */
class Loco
{
    private $RL;
    private $WL;
    private $CC;

    function __construct(
        ?String $readLockPropertyName = NULL,
        ?String $writeLockPropertyName = NULL,
        ?String $modCountPropertyName = NULL
    ){
        $this->RL = $readLockPropertyName ?? "Netmosfera\\Loco\\readLock";
        $this->WL = $writeLockPropertyName ?? "Netmosfera\\Loco\\writeLock";
        $this->CC = $modCountPropertyName ?? "Netmosfera\\Loco\\modCount";
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    protected function isLockedForRead($object){
        $isLockedForRead = ($object->{$this->RL} ?? 0) > 0;
        assert($isLockedForRead ? ($this->isLockedForWrite($object) === FALSE) : TRUE);
        return $isLockedForRead;
    }

    protected function lockForRead($object){
        assert($this->isLockedForWrite($object) === FALSE);
        $object->{$this->RL} = $object->{$this->RL} ?? 0;
        $object->{$this->RL}++;
    }

    protected function unlockForRead($object){
        assert($this->isLockedForRead($object));
        if($object->{$this->RL} === 1){
            unset($object->{$this->RL});
        }else{
            $object->{$this->RL}--;
        }
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    protected function isLockedForWrite($object){
        $isLockedForWrite = isset($object->{$this->WL});
        assert($isLockedForWrite ? ($this->isLockedForRead($object) === FALSE) : TRUE);
        return $isLockedForWrite;
    }

    protected function lockForWrite($object){
        assert($this->isLockedForWrite($object) === FALSE);
        assert($this->isLockedForRead($object) === FALSE);
        $object->{$this->WL} = TRUE;
    }

    protected function unlockForWrite($object){
        $this->isLockedForWrite($object);
        unset($object->{$this->WL});
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    protected function incrementVersion($object){
        if(isset($object->{$this->CC})){
            $object->{$this->CC}++;
            // @TBD this overflows to float after PHP_INT_MAX has been reached.
        }else{
            $object->{$this->CC} = PHP_INT_MIN;
        }
    }

    function getStateID($object){
        return $object->{$this->CC} ?? NULL;
    }

    function verifyStateID($object, $stateID){
        if($this->getStateID($object) !== $stateID){
            throw new LocoError($object, LocoError::ACTION_VERIFY_WEAK_READ_LOCK);
        }
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function readTransaction(Closure $code, $object){
        if($this->isLockedForWrite($object)){
            throw new LocoError($object, LocoError::ACTION_ACQUIRE_READ_LOCK);
        }
        $this->lockForRead($object);

        $return = $throwable = NULL;
        try{ $return = $code(); }
        catch(Throwable $throwable){}

        $this->unlockForRead($object);

        if($throwable !== NULL){ throw $throwable; }
        else{ return $return; }
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function writeTransaction(Closure $code, $object){
        $this->incrementVersion($object); // Increment before throwing, just in case

        if($this->isLockedForWrite($object) || $this->isLockedForRead($object)){
            throw new LocoError($object, LocoError::ACTION_ACQUIRE_WRITE_LOCK);
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
