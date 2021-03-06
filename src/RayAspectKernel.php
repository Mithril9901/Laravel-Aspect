<?php

/**
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 *
 * Copyright (c) 2015-2016 Yuuki Takezawa
 *
 */
namespace Ytake\LaravelAspect;

use Ray\Aop\Compiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Container\Container;
use Ytake\LaravelAspect\Exception\ClassNotFoundException;
use Ytake\LaravelAspect\Modules\AspectModule;

/**
 * Class RayAspectKernel
 */
class RayAspectKernel implements AspectDriverInterface
{
    /** @var Container|\Illuminate\Container\Container */
    protected $app;

    /** @var array */
    protected $configure;

    /** @var Compiler */
    protected $compiler;

    /** @var Filesystem */
    protected $filesystem;

    /** @var bool */
    protected $cacheable = false;

    /** @var AspectModule */
    protected $aspectResolver;

    /** @var AspectModule[] */
    protected $registerModules = [];

    /**
     * RayAspectKernel constructor.
     *
     * @param Container  $app
     * @param Filesystem $filesystem
     * @param array      $configure
     */
    public function __construct(Container $app, Filesystem $filesystem, array $configure)
    {
        $this->app = $app;
        $this->filesystem = $filesystem;
        $this->configure = $configure;
        $this->makeCompileDir();
        $this->makeCacheableDir();
        $this->compiler = $this->getCompiler();
        $this->registerAspectModule();
    }

    /**
     * @param null|string $module
     *
     * @throws ClassNotFoundException
     */
    public function register($module = null)
    {
        if (!class_exists($module)) {
            throw new ClassNotFoundException($module);
        }
        $this->aspectResolver = (new $module($this->app));
        $this->aspectResolver->attach();
    }

    /**
     * weaving
     */
    public function weave()
    {
        if (is_null($this->aspectResolver)) {
            return;
        }
        foreach ($this->aspectResolver->getResolver() as $class => $pointcuts) {
            $bind = (new AspectBind($this->filesystem, $this->configure['cache_dir'], $this->cacheable))
                ->bind($class, $pointcuts);
            $compiledClass = $this->compiler->compile($class, $bind);

            if (isset($this->app->contextual[$class])) {
                $this->resolveContextualBindings($class, $compiledClass);
            }
            $this->app->bind($class, function (Container $app) use ($bind, $compiledClass) {
                $instance = $app->make($compiledClass);
                $instance->bindings = $bind->getBindings();

                return $instance;
            });
        }
    }

    /**
     * @deprecated
     * boot aspect kernel
     */
    public function dispatch()
    {
        $this->weave();
    }

    /**
     * @return Compiler
     */
    protected function getCompiler()
    {
        return new Compiler($this->configure['compile_dir']);
    }

    /**
     * make source compile file directory
     *
     * @return void
     */
    protected function makeCompileDir()
    {
        $this->makeDirectories($this->configure['compile_dir'], 0777);
    }

    /**
     * make aspect cache directory
     *
     * @codeCoverageIgnore
     * @return void
     */
    protected function makeCacheableDir()
    {
        if ($this->configure['cache']) {
            $this->makeDirectories($this->configure['cache_dir'], 0777);
            $this->cacheable = true;
        }
    }

    /**
     * @param string $dir
     * @param int    $mode
     */
    private function makeDirectories($dir, $mode = 0777)
    {
        // @codeCoverageIgnoreStart
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->makeDirectory($dir, $mode, true);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * register Aspect Module
     */
    protected function registerAspectModule()
    {
        if (isset($this->configure['modules'])) {
            foreach ($this->configure['modules'] as $module) {
                $this->register($module);
            }
        }
    }

    /**
     * @param string $class
     * @param string $compiledClass
     */
    protected function resolveContextualBindings($class, $compiledClass)
    {
        foreach ($this->app->contextual[$class] as $abstract => $concrete) {
            $this->app->when($compiledClass)
                ->needs($abstract)
                ->give($concrete);
        }
    }
}
