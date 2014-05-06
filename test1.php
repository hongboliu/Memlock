<?php
/**
 * Script 'test1.php' and 'test2.php' are suppose to run under command line, not
 * a web server!
 * 
 * Open 2 command/shell windows, run 'test1.php' first, then quickly swith to
 * another window and run 'test2.php'. You should see the two script increasing
 * the value mutually. 'Server busy' message might show up if one of the script
 * can't acquire the lock.
 */

include 'Memlock.php';

set_time_limit(600);

$memcache = new Memcache();
$memcache->connect('127.0.0.1');

$key = 'memlock_test';
$value = 0;

$memcache->set($key, $value, 0, 3600);

echo "Test 1 begin with value: $value\n";

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

echo "Test 1 end with value: $value\n";
