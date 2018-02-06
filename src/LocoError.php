<?php declare(strict_types = 1); // atom

namespace Netmosfera\Loco;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use Error;
use function spl_object_id;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

/**
 * @TODOC
 */
class LocoError extends Error
{
    function __construct($object, Bool $acquiringWriteLock){
        if($acquiringWriteLock){
            parent::__construct(
                "Cannot acquire a write lock on object " .
                "`" . get_class($object) . "#" . spl_object_id($object) . "` " .
                " because it is being read or written by another party"
            );
        }else{
            parent::__construct(
                "Cannot acquire a read lock on object " .
                "`" . get_class($object) . "#" . spl_object_id($object) . "` " .
                " because it is being written by another party"
            );
        }
    }
}
