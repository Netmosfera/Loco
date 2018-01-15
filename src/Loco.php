<?php declare(strict_types = 1); // atom

namespace Netmosfera\Loco;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use Error;
use Closure;
use Throwable;
use ReflectionFunction;
use function spl_object_id;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

/**
 * @TODOC
 */
class Loco
{
    const ALLOW_FROM_SAME_SCOPE = TRUE;

    const DISALLOW_FROM_SAME_SCOPE = FALSE;

    private $locker;

    private $unlocker;

    private $determiner;

    function __construct(){
        $this->locker = function($object){
            $object->{"Netmosfera\\Loco\\lockEnabled"} = TRUE;
        };

        $this->unlocker = function($object){
            unset($object->{"Netmosfera\\Loco\\lockEnabled"});
        };

        $this->determiner = function($object){
            return $object->{"Netmosfera\\Loco\\lockEnabled"} ?? FALSE;
        };
    }

    function setLocker(Closure $locker){
        $this->locker = $locker;
    }

    function setUnlocker(Closure $unlocker){
        $this->unlocker = $unlocker;
    }

    function setDeterminer(Closure $determiner){
        $this->determiner = $determiner;
    }

    function call(Closure $code, ?String $callingScope, Bool $allowFromSameScope){
        $RF = new ReflectionFunction($code);

        $thisObject = $RF->getClosureThis();
        if($thisObject === NULL){
            throw new Error("In order to lock it, `\$this` is required to be bound to the `Closure`");
        }

        $closureScopeRC = $RF->getClosureScopeClass();
        $closureScope = $closureScopeRC === NULL ? NULL : $closureScopeRC->getName();
        $calledFromSameScope = $callingScope === $closureScope;

        $unlockWhenFinished = TRUE;

        if(($this->determiner)($thisObject) === TRUE){
            if($calledFromSameScope && $allowFromSameScope){
                $unlockWhenFinished = FALSE;
            }else{
                throw new LocoError("The object `" . get_class($thisObject) . "#" . spl_object_id($thisObject) . "` is locked at this point");
            }
        }

        ($this->locker)($thisObject);

        $return = $throwable = NULL;
        try{ $return = $code(); }
        catch(Throwable $throwable){}

        if($unlockWhenFinished){
            ($this->unlocker)($thisObject);
        }

        if($throwable !== NULL){ throw $throwable; }
        else{ return $return; }
    }
}
