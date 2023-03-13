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
                $this->connection_tries++;
                print("Failed. Retrying...\n");
                $this->connect();
            } else {
                die("Failed. Exiting.");
            }
        }

        print("Connected.\n\n");
    }

    public function query($query): mysqli_result|bool {
        if(DEBUG) {
            print("[Database] Query: $query\n");
            // Print a stack trace
            $trace = debug_backtrace();
            print("[Database] Called from: {$trace[1]["file"]}:{$trace[1]["line"]}\n");
        }

        // If the connection is dead then reconnect
        try {
            if(!$this->connection->ping()) {
                print("[Database] Connection is dead. Reconnecting...\n");
                $this->connect();
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            print("[Database] Connection is dead ($error_message). Reconnecting...\n");
            $this->connect();
        }

        try {
            $result = $this->connection->query($query);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            print("[Database] Query failed ($error_message): $query\n");
            return false;
        }

        return $result;
    }
}