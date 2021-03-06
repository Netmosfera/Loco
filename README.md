# Loco

Locking reads for PHP objects.

## Introduction 

This small library implements locking mechanisms for PHP objects. Its purpose is **avoiding
_by design_ disastrous errors like concurrent modifications or dirty reads**. It is meant
to be a development tool only: **users must not rely on the exceptions being thrown, they
should be avoided altogether by changing the code**. This way, it can be disabled in
production for better performance.

## Write locks (exclusive locks)

An object can be "locked for modifications". An active _write lock_ prevents other locks
(both _read locks_ and _write locks_) from being acquired. A _write lock_ cannot be acquired
if a _write lock_ or one or more _read locks_ are active.

## Read locks (shared locks)

An object can be "locked for read". An active _read lock_ prevents _write locks_ from being
acquired, but it does not prevent other _read locks_ from being acquired. For example the
objects `$b` and `$c` can both safely read in a concurrent manner from `$a`, because
modifications on `$a` are being denied. A _read lock_ cannot be acquired if a _write lock_
is active.

## Weak read locks

This kind of locks allows third parties to verify whether an object was not changed during
a period of time, without actually locking it. For example, `$client` relies on `$server`
being in the state `X`; after a period of time in which `$server` might have changed,
`$client` is asked to execute its functionality, but before doing that, it verifies that
`$server`'s state is still `X`. If it's not, it throws an error, otherwise it continues with
the execution of the requested task.

## Examples 

- [Locks example](examples/locks.php)
- [Weak read locks example](examples/weakReadLocks.php)

Note that the functions `locoRead`, `locoWrite` etc, are not bundled with the package (yet),
they must be defined in each project using _Loco_. 
