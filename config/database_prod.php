<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'u251673992_sistema_constr';
    private $username = 'u251673992_root_sistema_c';
    private $password = 'M47u*M47u';
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
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'"
                )
            );
            
            // Asegurar zona horaria Argentina (GMT -03:00)
            $this->conn->exec("SET time_zone = '-03:00'");
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
            die();
        }
        
        return $this->conn;
    }
}
?>
