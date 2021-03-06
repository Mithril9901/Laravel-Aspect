<?php

class RayAspectKernelTest extends \AspectTestCase
{
    /** @var \Ytake\LaravelAspect\RayAspectKernel */
    protected $kernel;
    public function setUp()
    {
        parent::setUp();
        $aspectConfigure = $this->app['config']->get('ytake-laravel-aop.aspect.drivers');
        $this->kernel = new \Ytake\LaravelAspect\RayAspectKernel(
            $this->app,
            $this->app['files'],
            $aspectConfigure['ray']
        );
    }

    /**
     * @expectedException \Ytake\LaravelAspect\Exception\ClassNotFoundException
     * @expectedExceptionMessage class not found at path: NotFoundClass
     */
    public function testExceptionCaseNotFoundClassRegister()
    {
        $this->kernel->register('NotFoundClass');
    }
}
