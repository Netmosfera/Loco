<?php declare(strict_types = 1); // atom

namespace Netmosfera\Loco;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use Closure;
use function PHPToolBucket\Bucket\callerClassScope;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

require "../vendor/autoload.php";

function locking(Closure $code, ?String $callingScope){
    assert((function() use(&$result, $code, $callingScope){
        $result = (new Loco())->call($code, $callingScope, TRUE);
        return TRUE;
    })());
    return isset($result) ? $result : $code();
}

class SafeClass
{
    function safeMethod(NastyCollaborator $collaborator){
        return locking(function() use($collaborator){
            return $collaborator->access();
        }, callerClassScope());
    }
}

class NastyCollaborator
{
    private $object;

    function __construct(SafeClass $object){
        $this->object = $object;
    }

    function access(){
        $this->object->safeMethod($this);
    }
}

$bar = new SafeClass();
$baz = new NastyCollaborator($bar);
$bar->safeMethod($baz);
