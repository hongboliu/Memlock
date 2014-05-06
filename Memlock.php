<?php

/**
 * Memlock - distributed locking service with memcached
 * 
 * Memlock requires Memcache extension
 * (http://www.php.net/manual/en/book.memcache.php) to function.
 * 
 * @author Hongbo Liu <hongbodev@gmail.com>
 */

/**
  The MIT License (MIT)

  Copyright (c) 2014 Hongbo Liu

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in
  all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  THE SOFTWARE.
 */

/**
 * Memlock
 * 
 * @author Hongbo Liu <hongbodev@gmail.com>
 */
class Memlock {

	const MEMCACHE_HOST = '127.0.0.1';

	/**
	 * The max time to hold a lock in seconds, recommanded to set to the same
	 * value as PHP INI Directive 'max_execution_time'.
	 */
	const EXPIRE = 30;

	/**
	 * Times of retry to acquire a lock before giving up. The interval between
	 * retries is random, from 10ms to 1 second. So 10 retries last 5 seconds
	 * averagely.
	 */
	const RETRY = 10;

	/**
	 * Prefix to all Memlock key to prevent memcache key collision against other
	 * codes.
	 */
	const PREFIX = 'memlock_';

	/**
	 * A key is the identification of a lock. Two Memlock objects with same key
	 * will represent a same lock.
	 * 
	 * @var string
	 */
	private $key;

	/**
	 * The actual key used with memcache, prefixed by const PREFIX to prevent
	 * memcache key collision against other codes.
	 * 
	 * @var string
	 */
	private $memcacheKey;

	/**
	 * Static array holds all the keys locked by the current script.
	 * 
	 * @var string[]
	 */
	private static $locked;

	/**
	 * Static Memcache instance used by all Memlock objects.
	 * 
	 * @var Memcache
	 */
	private static $memcache;

	/**
	 * Construct a Memlock with a $key as the identification.
	 * If $autoLock is set to TRUE (which is default), the returning lock will
	 * be acquired already.
	 * 
	 * @param string $key
	 * @param boolean $autoLock
	 */
	public function __construct($key, $autoLock = 1) {
		self::init();

		$this->key = $key;
		$this->memcacheKey = self::PREFIX . $key;

		if ($autoLock)
			$this->lock();
	}

	/**
	 * Acquire the lock. If the lock (or locks with same $key) is already
	 * acquired by the current script, it will return immediately.
	 * The method will simply return upon success. If the attempt failed, method
	 * onLockFailed() will be called.
	 */
	public function lock() {
		if ($this->isLocked())
			return;

		$success = 0;
		$retry = self::RETRY;

		do {
			$success = self::$memcache->add($this->memcacheKey, 1, 0, self::EXPIRE);

			if (!$success) {
				$retry--;

				if ($retry) {
					usleep(mt_rand(10000, 1000000));
				} else {
					$this->onLockFailed();
					return;
				}
			}
		} while (!$success);

		self::$locked[$this->key] = 1;
	}

	/**
	 * Release a locked lock. If the lock (or locks with same $key) is not
	 * locked or already released, it will return immediately.
	 */
	public function unlock() {
		if (!$this->isLocked())
			return;

		self::$memcache->delete($this->memcacheKey);

		unset(self::$locked[$this->key]);
	}

	/**
	 * Check whether the lock (or locks with same $key) is acquired by the
	 * current script.
	 * 
	 * @return boolean
	 */
	public function isLocked() {
		return isset(self::$locked[$this->key]);
	}

	public function key() {
		return $this->key;
	}

	/**
	 * Action to take when failed to acquire a lock.
	 * You should modify this method to fit your requirements, such as throwing
	 * an Exception and catch it in the caller.
	 */
	private function onLockFailed() {
		die('Server busy.');
	}

	/**
	 * Release any lock acquired by the end of this script.
	 * If script stops with error/exception, or the programmer simply forgot to
	 * unlock, all locks hold by the current script will be released
	 * automatically.
	 */
	public static function onShutdown() {
		if (empty(self::$locked))
			return;

		foreach (self::$locked as $key => $value) {
			$memcacheKey = self::PREFIX . $key;
			self::$memcache->delete($memcacheKey);
		}
	}

	private static function init() {
		if (self::$memcache)
			return;

		self::initMemcache();

		register_shutdown_function('Memlock::onShutdown');
	}

	private static function initMemcache() {
		self::$memcache = new Memcache();
		self::$memcache->pconnect(self::MEMCACHE_HOST);
	}

}
