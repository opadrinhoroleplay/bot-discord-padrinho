<?php
class DatabaseConnection {
    public  mysqli $connection; // Keep the connection public so we can access it from outside the class, for example, to use mysqli_real_escape_string()
    private int    $connection_tries = 0;
    private string $hostname;
    private string $username;
    private string $password;
    private string $database;

    public function __construct($hostname, $username, $password, $database) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;

        $this->connect();
    }

    public function connect() {
        try {
            print("[Database] Connecting to '$this->hostname'... ");
            $this->connection = new mysqli($this->hostname, $this->username, $this->password, $this->database);
        } catch (Exception $e) {
            if ($this->connection_tries < 3) {
                print("Failed: $e->getMessage()\n");
                $this->connection_tries++;
                $this->connect();
            } else {
                die("Failed. Exiting.");
            }
        }

        print("Connected.\n\n");
    }

    public function query($query): mysqli_result|bool {
        if(DEBUG) print("[Database] Query: $query\n");

        // If the connection is dead then reconnect
        try {
            if (!$this->connection->ping()) {
                print("[Database] Connection is dead. Reconnecting...\n");
                $this->connect();
            }
        } catch (Exception $e) {
            print("[Database] Failed to ping database: $e->getMessage()\n");
        }

        $result = $this->connection->query($query);
        if (!$result) echo "[Database] Query failed: ({$this->connection->errno} {$this->connection->error})\n";

        return $result;
    }
}