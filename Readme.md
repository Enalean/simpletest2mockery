SimpleTest to Mockery
=====================

An assistant to ease migration from SimpleTest mocks to Mockery ones.

Rational
--------

Last versions of [SimpleTest](https://github.com/simpletest/simpletest) struggle to have stable mock generation. On the
other hand, [Mockery](https://github.com/mockery/mockery) has a quite robust mock generation framework and it's more
advanced than ST one. Hence the intend of migration.

How to run it
-------------

* Clone this repository.
* Run composer install
* Then ./run.php /path/to/test/file.php

Then get a failure and contribute here with a PR to workaround the issue you got :)

How
---

This is entirely possible thanks to [@nikic](https://twitter.com/nikita_ppv) [PHP-Parser](https://github.com/nikic/PHP-Parser)
especially [Formatting-preserving pretty printing](https://github.com/nikic/PHP-Parser/blob/master/doc/component/Pretty_printing.markdown#formatting-preserving-pretty-printing).

What
----

At the moment, the current transformations are supported:
- Convert class level Mock::generate('XYZ') + new MockXYZ into \Mockery::spy(XYZ::class) in each test
- Convert class level Mock::generatePartial('XYZ') + new MockXYZ into \Mockery::mock(XYZ::class)->makePartial()->shouldAllowMockingProtectedMethods() in each test
- Remove top level Mock::generate*
- Convert setReturnValue, setReturnReference, expectOnce, expectNever, expectCallCount
- Inject parent::setUp() and parent::tearDown() when they are missing
- Convert dirname(__FILE__) to __DIR__ (because why not...)
- (specific to Tuleap test suite) Convert Tuleap mock() and partial_mock() wrappers

Plus, at the moment, it assume that you have a parent class as an intermediate for SimpleTest TestCase so you can have
in your `tearDown` method the mockery closing code:

```php
// Include mocker assertions into SimpleTest results
if ($container = \Mockery::getContainer()) {
    for ($i = 0; $i < $container->mockery_getExpectationCount(); $i++) {
        $this->pass();
    }
}
\Mockery::close();
```
