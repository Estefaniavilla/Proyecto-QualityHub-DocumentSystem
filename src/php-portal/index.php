<?php
// src/php-portal/index.php

// Definición de departamentos según tus notas de "Compañía A y B"
$departamentos = [
    'Compañía A' => ['Calidad', 'Recursos Humanos', 'Ventas'],
    'Compañía B' => ['Calidad', 'Recursos Humanos', 'Ventas']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QualityDoc | Portal de Consulta Pública</title>
    <style>
        :root { --primary: #2c3e50; --secondary: #34495e; --accent: #3498db; --white: #ffffff; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #ecf0f1; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: var(--white); padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h1 { color: var(--primary); text-align: center; margin-bottom: 0.5rem; font-size: 1.8rem; }
        .login-card p { color: #7f8c8d; text-align: center; margin-bottom: 2rem; font-size: 0.9rem; }
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; margin-bottom: 0.5rem; color: var(--secondary); font-weight: 600; }
        input, select { width: 100%; padding: 0.8rem; border: 1px solid #dcdde1; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; box-sizing: border-box; }
        input:focus, select:focus { border-color: var(--accent); outline: none; }
        .btn-login { width: 100%; padding: 0.8rem; background-color: var(--accent); color: white; border: none; border-radius: 6px; font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 1rem; }
        .btn-login:hover { background-color: #2980b9; }
        .footer-text { text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: #bdc3c7; }
    </style>
</head>
<body>

<div class="login-card">
    <h1>QualityDoc</h1>
    <p>Portal de Consulta Pública y Reportes</p>
    
    <!-- Este formulario enviará los datos a dashboard.php -->
    <form action="dashboard.php" method="POST">
        <div class="form-group">
            <label for="usuario">Usuario / Nómina</label>
            <input type="text" id="usuario" name="usuario" placeholder="Ej. ESTEF123" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="form-group">
            <label for="departamento">Seleccione su Ubicación</label>
            <select id="departamento" name="departamento" required>
                <option value="" disabled selected>Seleccione empresa y área...</option>
                <?php foreach ($departamentos as $empresa => $areas): ?>
                    <optgroup label="<?php echo $empresa; ?>">
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo "{$empresa}_{$area}"; ?>">
                                <?php echo "{$area} ({$empresa})"; ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-login">Ingresar al Portal</button>
    </form>

    <div class="footer-text">
        &copy; 2026 QualityHub Document Management System
    </div>
</div>

</body>
</html> 

