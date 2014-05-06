<?php
/**
 * See description in test1.php
 */

include 'Memlock.php';

set_time_limit(600);

$memcache = new Memcache();
$memcache->connect('127.0.0.1');

$key = 'memlock_test';
$value = $memcache->get($key);

echo "Test 2 begin with value: $value\n";

for ($i = 0; $i < 1000; $i++) {
	$lock = new Memlock($key);

	$value = $memcache->get($key);
	$value++;

	echo "$value\n";

	usleep(100000);

	$memcache->set($key, $value, 0, 60);

	$lock->unlock();

	usleep(100000);
}

echo "Test 2 end with value: $value\n";
