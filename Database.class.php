<?php
class DatabaseConnection {
    public  mysqli $handle; // Keep the handle public so we can access it from outside the class, for example, to use mysqli_real_escape_string()
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

    public function connect(): int|bool {
        $this->handle = new mysqli($this->hostname, $this->username, $this->password, $this->database);
        if ($this->handle->connect_errno) {
            echo "Failed to connect to MySQL: (" . $this->handle->connect_errno . ") " . $this->handle->connect_error;

            return false;
        }

        return $this->handle;
    }

    public function query($query): mysqli_result|bool {
        $result = $this->handle->query($query);
        if (!$result) {
            echo "Query failed: (" . $this->handle->errno . ") " . $this->handle->error;
        }
        return $result;
    }

    public function close() {
        $this->handle->close();
    }
}