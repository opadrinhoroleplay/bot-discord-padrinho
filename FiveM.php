<?php
class FiveM {
    
    /*
    Scrape https://status.cfx.re/
    Get the last rect element in the first svg element
    Get the fill attribute of that element of the rect
    If it's #05f4a7 then it's online, otherwise it's offline
    */
    static function Status(callable $callback = null): bool|null {
        static $last_status = null;

        $doc = new DOMDocument();
        @$doc->loadHTML(file_get_contents("https://status.cfx.re/"));
        $xpath = new DOMXPath($doc);
        $rect  = $xpath->query("//svg/rect[last()]")[0]; // Other elements are generated during runtime so this seemed the best bet
        $color = $rect->getAttribute("fill");

        if(!$color) return null; // If the color is empty, then the website is probably down

        $curr_status = $color === "#05f4a7" ? true : false;

        // If the status has changed, call the callback
        if ($curr_status !== $last_status) {
            if($callback && $last_status != null) $callback($curr_status); // Only call if requested and if it's not the first run
            $last_status = $curr_status;
        }

        return $curr_status;
    }
}