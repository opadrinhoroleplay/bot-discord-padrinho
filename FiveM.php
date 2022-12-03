<?php
class FiveM {
    static public $last_status = null;

    /*
        Scrape https://status.cfx.re/
        Get the last rect element in the first svg element
        Get the fill attribute of that element of the rect
        If it's #05f4a7 then it's online, otherwise it's offline
        */
    static function Status(callable $callback = NULL): bool {
        $doc = new DOMDocument();
        @$doc->loadHTML(file_get_contents("https://status.cfx.re/"));
        $xpath = new DOMXPath($doc,);
        $rect  = $xpath->query("//svg/rect[last()]")[0]; // Other elements are generated during runtime so this seemed the best bet
        $color = $rect->getAttribute("fill");

        $status = $color === "#05f4a7" ? true : false;

        if (self::$last_status !== $status) {
            $callback($status);
            self::$last_status = $status;
        }

        return $status;
    }
}