<?php
class FiveM {
    static public $last_status = null;

    /*
        Scrape https://status.cfx.re/
        Get the last rect element in the first svg element
        Get the fill attribute of that element of the rect
        If it's #05f4a7 then it's online, otherwise it's offline
    */
    static function Status(callable $callback = null): bool|null {
        $doc = new DOMDocument();
        @$doc->loadHTML(file_get_contents("https://status.cfx.re/"));
        $xpath = new DOMXPath($doc);
        $rect  = $xpath->query("//svg/rect[last()]")[0]; // Other elements are generated during runtime so this seemed the best bet
        $color = $rect->getAttribute("fill");

        if(!$color) return null; // If the color is empty, then the website is probably down

        $status = $color === "#05f4a7" ? true : false;

        // If the status has changed, call the callback
        if (self::$last_status !== $status) {
            if(self::$last_status != null) $callback($status); // Don't call the callback on the first run
            self::$last_status = $status;
        }

        return $status;
    }
}