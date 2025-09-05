<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'sistema_constructora';
    private $username = 'root';
    private $password = 'root'; //se usa root como contraseña desde la notebook en local
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
            die();
        }
        
        return $this->conn;
    }
}
?>
