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
    const ACTION_ACQUIRE_WRITE_LOCK = 1;
    const ACTION_ACQUIRE_READ_LOCK = 2;
    const ACTION_VERIFY_WEAK_READ_LOCK = 3;

    function __construct($object, Int $action){
        if($action === self::ACTION_ACQUIRE_WRITE_LOCK){
            parent::__construct(
                "Cannot acquire a write lock on object " .
                "`" . get_class($object) . "#" . spl_object_id($object) . "` " .
                " because it is being read or written by another party"
            );
        }elseif($action === self::ACTION_ACQUIRE_READ_LOCK){
            parent::__construct(
                "Cannot acquire a read lock on object " .
                "`" . get_class($object) . "#" . spl_object_id($object) . "` " .
                " because it is being written by another party"
            );
        }elseif($action === self::ACTION_VERIFY_WEAK_READ_LOCK){
            parent::__construct(
                "The object " .
                "`" . get_class($object) . "#" . spl_object_id($object) . "` " .
                " was modified concurrently by another party"
            );
        }
    }
}
