<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityDoc | Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-cian: #00f2ff;
            --dark-bg: #0a0e17;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --text-white: #ffffff;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at center, #1a2a44 0%, #0a0e17 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        /* Fondo animado sutil */
        body::before {
            content: "";
            position: absolute;
            width: 200%;
            height: 200%;
            background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            opacity: 0.1;
            z-index: -1;
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            width: 400px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .logo-section h1 {
            color: var(--primary-cian);
            margin: 0;
            font-size: 2.2rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .logo-section p {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        .avatar-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .avatar-group i {
            font-size: 50px;
            color: rgba(255, 255, 255, 0.2);
            border: 2px solid var(--primary-cian);
            padding: 15px;
            border-radius: 50%;
            transition: 0.3s;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-cian);
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: white;
            outline: none;
            box-sizing: border-box;
            transition: 0.3s;
        }

        .input-group input:focus, .input-group select:focus {
            border-color: var(--primary-cian);
            box-shadow: 0 0 10px rgba(0, 242, 255, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(45deg, #0072ff, #00f2ff);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(0, 242, 255, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 242, 255, 0.5);
        }

        .footer-text {
            margin-top: 25px;
            font-size: 0.8rem;
            color: #666;
        }

        /* Estilo para el select */
        select option {
            background: #1a2a44;
            color: white;
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
        <i class="fa-solid fa-user-tie"></i>
       <i class="fa-solid fa-user-gear"></i>
    </div>

    <form action="login_process.php" method="POST">
        <div class="input-group">
            <i class="fa-solid fa-id-card"></i>
            <input type="text" name="nomina" placeholder="Usuario / Nómina" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Contraseña" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-building"></i>
            <select name="ubicacion" required>
                <option value="" disabled selected>Seleccione Empresa y Área</option>
                <optgroup label="Empresa Alpha">
                    <option value="alpha_calidad">Alpha - Calidad</option>
                    <option value="alpha_prod">Alpha - Producción</option>
                </optgroup>
                <optgroup label="Empresa Beta">
                    <option value="beta_admin">Beta - Administración</option>
                    <option value="beta_log">Beta - Logística</option>
                </optgroup>
            </select>
        </div>

        <button type="submit" class="btn-login">Ingresar al Portal</button>
    </form>

    <div class="footer-text">
        © 2026 QualityHub Document System
    </div>
</div>

</body>
</html>
