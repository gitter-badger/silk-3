<?php

namespace Silk;

use Illuminate\Support\Collection;

class Hook
{
    protected $handle;

    protected $callback;

    protected $callbackParamCount;

    protected $priority;

    protected $iterations;

    protected $maxIterations;

    /**
     * @var Collection
     */
    protected $conditions;


    /**
     * Create a new Hook instance
     *
     * @param  string $handle action or filter handle
     * @param  int    $priority
     * @return static         instance
     */
    public static function on($handle, $priority = 10)
    {
        return new static($handle, $priority);
    }

    /**
     * Create a new Hook instance
     * @param  string $handle action or filter handle
     * @param  int    $priority
     */
    public function __construct($handle, $priority = 10)
    {
        $this->handle = $handle;
        $this->priority = $priority;
        $this->conditions = new Collection([
            new Callback([$this, 'hasNotExceededIterations'])
        ]);
    }

    /**
     * Set the callback invoked by the hook
     *
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = new Callback($callback);
        $this->callbackParamCount = $this->callback->parameterCount();

        return $this;
    }

    /**
     * Set the hook in WP
     *
     * @return $this
     */
    public function listen()
    {
        add_filter($this->handle, [$this, 'mediateCallback'], $this->priority, 100);

        return $this;
    }

    /**
     * Unset the hook in WP
     *
     * @return $this
     */
    public function remove()
    {
        remove_filter($this->handle, [$this, 'mediateCallback'], $this->priority);

        return $this;
    }

    /**
     * Handle the callback from WordPress
     *
     * @return mixed
     */
    public function mediateCallback($given = null)
    {
        if (! $this->shouldInvoke(func_get_args())) {
            return $given;
        }

        return $this->invokeCallback(func_get_args());
    }

    /**
     * Whether or not the callback should be invoked
     *
     * @param  array  $args arguments being passed to the callback
     *
     * @return boolean
     */
    protected function shouldInvoke(array $args)
    {
        /**
         * Find the first condition which returns false.
         * If it returns anything, that means that a condition failed,
         * thus the callback should not be invoked!
         */
        return ! $this->conditions->first(function ($index, Callback $callback) use ($args) {
            return false === $callback->callArray($args);
        });
    }

    /**
     * Invoke the registered callback
     *
     * @param  array $arguments
     *
     * @return mixed callback Returns the return value of the callback.
     */
    protected function invokeCallback($arguments)
    {
        $arguments = array_slice($arguments, 0, $this->callbackParamCount ?: null);

        $this->iterations++;

        return $this->callback->callArray($arguments);
    }

    /**
     * Limit the callback to only be invoked once
     *
     * @return $this
     */
    public function once()
    {
        $this->onlyXtimes(1);

        return $this;
    }

    /**
     * Limit the number of times the callback can be invoked
     *
     * @param  int $times
     *
     * @return $this
     */
    public function onlyXtimes($times)
    {
        $this->maxIterations = (int) $times;

        return $this;
    }

    /**
     * Prevent the callback from being invoked again
     *
     * @return $this
     */
    public function bypass()
    {
        $this->onlyXtimes(0);

        return $this;
    }

    /**
     * Set the registered priority of the callback on the given hook.
     *
     * @param  mixed $priority  Hook priority
     *
     * @return $this
     */
    public function withPriority($priority)
    {
        $this->remove();

        $this->priority = $priority;

        $this->listen();

        return $this;
    }

    /**
     * Add a condition to control invocation of the callback
     *
     * If the condition returns FALSE, the hook's callback will not be invoked.
     *
     * @param  callable $callback
     *
     * @return $this
     */
    public function onlyIf(callable $callback)
    {
        $this->conditions->push(new Callback($callback));

        return $this;
    }

    /**
     * If the callback has NOT exceeded the limit of allowed iterations
     *
     * @return boolean  true for NOT exceeded, otherwise false
     */
    public function hasNotExceededIterations()
    {
        return ! $this->hasExceededIterations();
    }

    /**
     * Whether or not the callback has met the limit of allowed iterations
     *
     * @return boolean  true for exceeded, otherwise false
     */
    public function hasExceededIterations()
    {
        return ($this->maxIterations > -1) && ($this->iterations >= $this->maxIterations);
    }
}
