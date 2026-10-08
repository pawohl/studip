<?php

namespace Trails;

use Trails\Exceptions\SessionRequiredException;

/**
 * The flash provides a way to pass temporary objects between actions.
 * Anything you place in the flash will be exposed to the very next action and
 * then cleared out. This is a great way of doing notices and alerts, such as
 * a create action that sets
 * <tt>$flash->set('notice', "Successfully created")</tt>
 * before redirecting to a display action that can then expose the flash to its
 * template.
 *
 * @package       trails
 *
 * @author        mlunzena
 * @copyright (c) Authors
 * @version       $Id: trails.php 7001 2008-04-04 11:20:27Z mlunzena $
 */
final class Flash implements \ArrayAccess, \IteratorAggregate
{
    private array $flash = [];
    private array $used = [];

    /**
     * @return self
     * @throws SessionRequiredException
     */
    public static function instance()
    {
        if (!isset($_SESSION)) {
            throw new Exceptions\SessionRequiredException();
        }

        if (!isset($_SESSION[self::class])) {
            $_SESSION[self::class] = new self();
        }
        return $_SESSION[self::class];
    }

    public function offsetExists($offset): bool
    {
        return isset($this->flash[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->flash[$offset], $this->used[$offset]);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->flash);
    }

    /**
     * Used internally by the <tt>keep</tt> and <tt>discard</tt> methods
     *     use()               # marks the entire flash as used
     *     use('msg')          # marks the "msg" entry as used
     *     use(null, false)    # marks the entire flash as unused
     *                         # (keeps it around for one more action)
     *     use('msg', false)   # marks the "msg" entry as unused
     *                         # (keeps it around for one more action)
     *
     */
    private function use(?string $key = null, bool $isUsed = true): void
    {
        if ($key) {
            $this->used[$key] = $isUsed;
        } else {
            foreach (array_keys($this->used) as $key) {
                $this->use($key, $isUsed);
            }
        }
    }

    /**
     * Marks the entire flash or a single flash entry to be discarded by the end
     * of the current action.
     *
     *     $flash->discard()             # discards entire flash
     *                                   # (it'll still be available for the
     *                                   # current action)
     *     $flash->discard('warning')    # discard the "warning" entry
     *                                   # (it'll still be available for the
     *                                   # current action)
     */
    public function discard(?string $key = null): void
    {
        $this->use($key);
    }

    /**
     * Returns the value to the specified key.
     *
     * @return mixed the key's value.
     */
    public function &get(string $key): mixed
    {
        $return = null;
        if (isset($this->flash[$key])) {
            $return =& $this->flash[$key];
        }
        return $return;
    }

    /**
     * Keeps either the entire current flash or a specific flash entry available
     * for the next action:
     *
     *    $flash->keep()           # keeps the entire flash
     *    $flash->keep('notice')   # keeps only the "notice" entry, the rest of
     *                             # the flash is discarded
     */
    public function keep(?string $key = null): void
    {
        $this->use($key, false);
    }

    /**
     * Sets a key's value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->keep($key);
        $this->flash[$key] = $value;
    }

    /**
     * Sets a key's value by reference.
     */
    public function set_ref(string $key, mixed &$value): void
    {
        $this->keep($key);
        $this->flash[$key] =& $valze;
    }

    /**
     * Removes all used values
     */
    public function sweep(): void
    {
        foreach (array_keys($this->flash) as $k) {
            if ($this->used[$k]) {
                unset($this->flash[$k], $this->used[$k]);
            } else {
                $this->use($k);
            }
        }
    }

    public function __toString()
    {
        $values = [];
        foreach ($this->flash as $key => $value) {
            $values[] = sprintf(
                "'%s': [%s, '%s']",
                $key,
                var_export($value, true),
                !empty($this->used[$key]) ? 'used' : 'unused'
            );
        }
        return '{' . implode(', ', $values) . '}'. "\n";
    }

    public function __sleep(): array
    {
        $this->sweep();
        return ['flash', 'used'];
    }


    public function __wakeUp()
    {
        $this->discard();
    }
}
