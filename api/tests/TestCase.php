<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The connections that should be transacted during testing.
     *
     * @var array
     */
    protected $connectionsToTransact = ['pgsql', 'platform'];
}
