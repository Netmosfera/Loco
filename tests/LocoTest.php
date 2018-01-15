<?php declare(strict_types = 1); // atom

namespace Netmosfera\LocoTests;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

use function debug_backtrace;
use Error;
use Netmosfera\Loco\Loco;
use Netmosfera\Loco\LocoError;
use function PHPToolBucket\Bucket\callerClassScope;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

//[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

class LocoTest extends TestCase
{
    function test_lock_other_class(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa($concurrent){
                return (new Loco)->call(function() use($concurrent){
                    $concurrent->bbb($this);
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }
        };

        $securedObject->aaa(new class(){
            function bbb($securedObject){
                $securedObject->aaa(123);
            }
        });
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_lock_global_scope_1(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa($concurrent){
                $dt = debug_backtrace();
                $cs = callerClassScope();
                return (new Loco)->call(function() use($concurrent){
                    $concurrent();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }
        };

        $securedObject->aaa(function() use($securedObject){
            $securedObject->aaa(123);
        });
    }

    function test_lock_global_scope_2(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa($concurrent){
                return (new Loco)->call(function() use($concurrent){
                    $concurrent();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }
        };

        $securedObject->aaa(function() use($securedObject){
            $securedObject->aaa(123);
        });
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_lock_sibling_call_disallowed(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa(){
                return (new Loco)->call(function(){
                    $this->bbb();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function bbb(){
                return (new Loco)->call(function(){
                    throw new Error("FAILURE");
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }
        };

        $securedObject->aaa();
    }

    function test_lock_sibling_call_allowed(){
        $securedObject = new class(){
            function aaa(){
                return (new Loco)->call(function(){
                    return $this->bbb();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }

            function bbb(){
                return (new Loco)->call(function(){
                    return "WORKS";
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }
        };

        self::assertSame("WORKS", $securedObject->aaa());
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_lock_sibling_call_allowed_deep(){
        $securedObject = new class(){
            function aaa(){
                return (new Loco)->call(function(){
                    return $this->bbb();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }

            function bbb(){
                return (new Loco)->call(function(){
                    return $this->ccc();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function ccc(){
                return (new Loco)->call(function(){
                    return "WORKS";
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }
        };

        self::assertSame("WORKS", $securedObject->aaa());
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_lock_sibling_call_disallowed_deep(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa(){
                return (new Loco)->call(function(){
                    return $this->bbb();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }

            function bbb(){
                return (new Loco)->call(function(){
                    return $this->ccc();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function ccc(){
                return (new Loco)->call(function(){
                    return $this->ddd();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function ddd(){
                return (new Loco)->call(function(){
                    return "WORKS";
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }
        };

        self::assertSame("WORKS", $securedObject->aaa());
    }

    //[][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][][]

    function test_lock_is_not_released_after_a_sibling_is_allowed_to_call_while_locked(){
        $this->expectException(LocoError::CLASS);

        $securedObject = new class(){
            function aaa(){
                return (new Loco)->call(function(){
                    $this->f1 = $this->bbb();
                    $this->f2 = $this->bbb();
                    $this->ddd();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }

            function bbb(){
                return (new Loco)->call(function(){
                    return $this->ccc();
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function ccc(){
                return (new Loco)->call(function(){
                    return "WORKS";
                }, callerClassScope(), Loco::ALLOW_FROM_SAME_SCOPE);
            }

            function ddd(){
                return (new Loco)->call(function(){
                    throw new Error();
                }, callerClassScope(), Loco::DISALLOW_FROM_SAME_SCOPE);
            }
        };

        try{
            $securedObject->aaa();
            self::assertSame(FALSE, TRUE);
        }catch(LocoError $e){}

        self::assertSame("WORKS", $securedObject->f1);
        self::assertSame("WORKS", $securedObject->f2);

        $securedObject->aaa();
    }
}
