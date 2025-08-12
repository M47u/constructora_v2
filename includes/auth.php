<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function login($email, $password) {
        try {
            $query = "SELECT id_usuario, nombre, apellido, email, contraseña, rol, estado 
                     FROM usuarios 
                     WHERE email = :email AND estado = 'activo'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                if (password_verify($password, $user['contraseña'])) {
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id_usuario'];
                    $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['rol'];
                    $_SESSION['login_time'] = time();
                    
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        redirect(SITE_URL . '/login.php');
    }
    
    public function register($nombre, $apellido, $email, $password, $rol) {
        try {
            // Verificar si el email ya existe
            $check_query = "SELECT id_usuario FROM usuarios WHERE email = :email";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }
            
            // Insertar nuevo usuario
            $query = "INSERT INTO usuarios (nombre, apellido, email, contraseña, rol) 
                     VALUES (:nombre, :apellido, :email, :password, :rol)";
            
            $stmt = $this->conn->prepare($query);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':apellido', $apellido);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':rol', $rol);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
            }
            
            return ['success' => false, 'message' => 'Error al registrar usuario'];
            
        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error interno del servidor'];
        }
    }
    
    public function check_session() {
        if (!is_logged_in()) {
            redirect(SITE_URL . '/login.php');
        }
        
        // Verificar timeout de sesión (2 horas)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 7200) {
            $this->logout();
        }
    }
}
?>
