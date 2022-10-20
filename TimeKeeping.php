<?php
use React\EventLoop\Loop;

class TimeKeeping {
    static function hour(callable $callback) {
        // Sync to the next hour so we can start the hourly timer
        Loop::addPeriodicTimer(60, function ($timer) use ($callback) {
            if(date('i') != 00) return; // Ignore all other minutes

            $callback(date("H")); // Call on the first hour detected

            // Create hour scanner
            Loop::addPeriodicTimer(60*60, function () use ($callback) {$callback(date("H"));});
            // We don't need the first timer anymore
            Loop::cancelTimer($timer);
        });
    }
}