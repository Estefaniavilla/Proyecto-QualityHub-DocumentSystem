<?php
// Aseguramos que la sesión inicie limpia y destruya rastros anteriores si se viene de un logout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php'; 

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomina = trim($_POST['nomina']);
    $password = trim($_POST['password']);
    $ubicacion_elegida = isset($_POST['ubicacion']) ? trim($_POST['ubicacion']) : '';

    if (!empty($nomina) && !empty($password) && !empty($ubicacion_elegida)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE nomina = :nomina LIMIT 1");
            $stmt->execute([':nomina' => $nomina]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $password === $user['password'] && $ubicacion_elegida === $user['usuario_ubicacion']) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_nomina'] = $user['nomina'];
                $_SESSION['usuario_ubicacion'] = $user['usuario_ubicacion']; 

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Acceso denegado. Credenciales o área incorrectas.";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión con la base de datos: " . $e->getMessage();
        }
    } else {
        $error = "Por favor, completa todos los campos del formulario.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityDoc | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* 🎨 NUEVA TEMÁTICA CLARA INTEGRAL DE ACUERDO AL CUERPO DEL DASHBOARD */
        :root {
            --primary-blue: #0284c7; /* Azul corporativo del menú */
            --primary-hover: #0369a1; 
            --bg-light: #f8fafc; /* Gris/azul claro de tu dashboard principal */
            --card-bg: #ffffff; /* Blanco puro */
            --text-dark: #0f172a; /* Slate oscuro para máxima lectura */
            --text-muted: #37619c; /* Subtítulos e íconos */
            --border-color: #e2e8f0; /* Bordes sutiles limpios */
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #2d568c 0%, var(--bg-light) 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Textura sutil industrial de fondo */
        body::before {
            content: "";
            position: absolute;
            width: 200%;
            height: 200%;
            background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            opacity: 0.02;
            z-index: -1;
        }

        /* Tarjeta Blanca Limpia */
        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 45px 40px;
            width: 410px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
            text-align: center;
            box-sizing: border-box;
        }

        .logo-section h1 {
            color: var(--primary-blue);
            margin: 0;
            font-size: 2.3rem;
            letter-spacing: -0.5px;
            font-weight: 700;
        }

        .logo-section p {
            color: var(--text-muted);
            font-size: 0.92rem;
            margin-top: 6px;
            margin-bottom: 35px;
            font-weight: 500;
        }

        .avatar-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 35px;
        }

        .avatar-box {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            border: 1px solid var(--border-color);
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: 0.2s ease;
        }

        .avatar-box i {
            font-size: 30px;
            color: #94a3b8;
            transition: 0.2s ease;
        }

        .avatar-box:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            background: #e2e8f0;
        }

        .avatar-box:hover i {
            color: var(--primary-blue);
        }

        .input-group {
            position: relative;
            margin-bottom: 22px;
        }

        .input-group i.input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            z-index: 2;
            pointer-events: none;
        }

        /* Inputs Claros Estilo Moderno */
        .input-group input, .input-group select {
            width: 100%;
            padding: 13px 15px 13px 48px;
            background: #f8fafc !important;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-dark) !important;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            outline: none;
            box-sizing: border-box;
            transition: 0.2s ease;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        /* Compatibilidad de autocompletado en navegadores */
        .input-group input:-webkit-autofill {
            -webkit-text-fill-color: var(--text-dark) !important;
            -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        .input-group select {
            cursor: pointer;
            padding-right: 40px;
        }

        /* Flecha del selector */
        .input-group.select-container::after {
            content: "\f107";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }

        .input-group input:focus, .input-group select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
            background: #ffffff !important;
        }

        select option {
            background: #ffffff;
            color: var(--text-dark);
            padding: 12px;
        }

        select optgroup {
            background: #f1f5f9;
            color: var(--primary-blue);
            font-weight: 600;
        }

        /* Botón Azul Reactivo */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--primary-blue);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            transition: 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(2, 132, 199, 0.1), 0 2px 4px -1px rgba(2, 132, 199, 0.06);
            letter-spacing: 0.5px;
            margin-top: 5px;
            font-size: 0.9rem;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            box-shadow: 0 10px 15px -3px rgba(2, 132, 199, 0.25);
        }

        /* Alerta de Error */
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            padding: 11px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .footer-text {
            margin-top: 30px;
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-section">
        <h1>QualityDoc</h1>
        <p>Portal de Consulta Pública y Reportes</p>
    </div>

    <div class="avatar-group">
        <div class="avatar-box">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="avatar-box">
            <i class="fa-solid fa-user-gear"></i> 
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="" method="POST" id="loginForm" autocomplete="off">
        
        <div class="input-group">
            <i class="fa-solid fa-id-card input-icon"></i>
            <input type="text" name="nomina" id="inputNomina" placeholder="Usuario / Nómina" required autocomplete="new-password">
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="password" name="password" id="inputPassword" placeholder="Contraseña" required autocomplete="new-password">
        </div>

        <div class="input-group select-container">
            <i class="fa-solid fa-building input-icon"></i>
            <select name="ubicacion" id="selectUbicacion" required>
                <option value="" disabled selected>Seleccione Empresa y Área</option>
                <optgroup label="Empresa Alpha">
                    <option value="Alpha - Calidad">Alpha - Calidad</option>
                    <option value="Alpha - Producion">Alpha - Producción</option>
                </optgroup>
                <optgroup label="Empresa Beta">
                    <option value="Beta - Administracion">Beta - Administración</option>
                    <option value="Beta - Logistica">Beta - Logística</option>
                </optgroup>
            </select>
        </div>

        <button type="submit" class="btn-login">Ingresar al Portal</button>
    </form>

    <div class="footer-text">
        © 2026 QualityHub Document System
    </div>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const formulario = document.getElementById('loginForm');
        if (formulario) {
            formulario.reset();
        }

        setTimeout(() => {
            document.getElementById('inputNomina').value = '';
            document.getElementById('inputPassword').value = '';
            document.getElementById('selectUbicacion').selectedIndex = 0;
        }, 50); 
    });
</script>

</body>
</html>
