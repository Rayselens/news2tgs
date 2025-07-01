<?php
    class DatabaseConnection
    {
        private $host;
        private $port;
        private $dbname;
        private $user;
        private $password;

        public function __construct($host, $port, $dbname, $user, $password){
            $this->host = $host;
            $this->port = $port;
            $this->dbname = $dbname;
            $this->user = $user;
            $this->password = $password;
        }

        public function DbConnection(){
            return pg_connect("host={$this->host} port={$this->port} dbname={$this->dbname} user={$this->user} password={$this->password}");
        }
    }
?>