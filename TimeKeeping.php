<?php
use React\EventLoop\Loop;

class TimeKeeping {
    static function hour(callable $callback) {
        // Sync to the next hour so we can start the hourly timer
        Loop::addPeriodicTimer(60, function ($timer) use ($callback) {
            if(date('i') != 00) return; // Ignore all other minutes until we hit the next hour
            Loop::cancelTimer($timer); // Cancel the timer so we don't keep running this

            $callback(date("H")); // Call on the first hour detected

            // Create hour scanner
            Loop::addPeriodicTimer(60*60, function () use ($callback) {
                // Check if mysql connection is still alive
                if(!mysqli_ping($GLOBALS["db"])) {
                    $config = $GLOBALS["config"]; 
                    if($GLOBALS["db"] = mysqli_connect("p:{$config->database->host}", $config->database->user, $config->database->pass, $config->database->database)) {
                        print("Reconnected to database");
                    } else {
                        echo "Failed to connect to MySQL: " . mysqli_connect_error();
                        exit;
                    }
                }
                
                $callback(date("H"));
            });
        });
    }
}