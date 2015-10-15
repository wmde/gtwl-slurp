<?php

/**
 * @licence GPLv2+
 * @author Addshore
 *
 * @todo output as a wikitable or something like that?
 * @todo CLI option to translate to english?
 */

// Loading

require_once( __DIR__ . '/vendor/autoload.php' );

// Runtime

$slurper = new GtwlSlurp\Slurper( array(
	// TODO cli var?
	// Note: Uncomment to translate the output to english! :D
	'translate' => 'en',
	'highlight' => 20,
) );

echo "Running\n";
echo $slurper->run();