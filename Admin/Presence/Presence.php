<?php
namespace Admin;

use DatabaseConnection;

use Admin\Presence\Rollcall\RollcallMessage;

use Discord\Parts\Channel\Channel;

class Presence {
    static private DatabaseConnection $db;
    static private Channel $admin_channel;

    static function Init()
    {
        print("Initializing presence module...\n");

        self::$db = $GLOBALS["db"];
        self::$admin_channel = $GLOBALS["channel_admin"];

        // Take care of the Rollcall
        $result = self::$db->query("SELECT value FROM discord_settings WHERE name = 'rollcall' AND DATE(last_updated) = CURDATE()");

        return new RollcallMessage($result->fetch_column() ?? null); // If there's no rollcall data, pass null
    }

    static function Rollcall() {
        return new RollcallMessage();
    }    
}