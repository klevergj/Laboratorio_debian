<?php
// === LÓGICA DE DETECCIÓN DEL SISTEMA (PHP REAL) ===

// 1. Obtener uso de CPU (Promedio de carga en el último minuto)
$load = sys_getloadavg();
$cpu_usage = isset($load[0]) ? round(($load[0] * 100), 1) : "N/D";
if ($cpu_usage > 100) $cpu_usage = 100; // Limitar visualmente a 100%

// 2. Obtener uso de Memoria RAM leyendo /proc/meminfo de Linux
$free_output = shell_exec('free -m');
$mem_used = 0;
$mem_total = 1;
if ($free_output) {
    $lines = explode("\n", trim($free_output));
    if (isset($lines[1])) {
        $stats = preg_split('/ +/', $lines[1]);
        $mem_total = $stats[1];
        $mem_used = $stats[2];
    }
}
$ram_percentage = round(($mem_used / $mem_total) * 100, 1);

// 3. Obtener uso de Disco Duro de la raíz '/'
$disk_total = disk_total_space("/");
$disk_free = disk_free_space("/");
$disk_used = $disk_total - $disk_free;
$disk_percentage = round(($disk_used / $disk_total) * 100, 1);

// Convertir bytes a GB legibles
$disk_total_gb = round($disk_total / (1024 * 1024 * 1024), 1);
$disk_used_gb = round($disk_used / (1024 * 1024 * 1024), 1);

// 4. Datos adicionales
$server_ip = $_SERVER['SERVER_ADDR'] ?? shell_exec("hostname -I | awk '{print $1}'") ?? "127.0.0.1";
$uptime = shell_exec("uptime -p") ?? "Desconocido";
$kernel = shell_exec("uname -r") ?? "Linux";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Linux de Mauri</title>
    <style>
        /* === ESTILOS GENERALES Y FONDO ANIMADO === */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        body {
            background: radial-gradient(circle at center, #1a1c29 0%, #0e1017 100%);
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            position: relative;
            padding: 20px;
        }

        /* Partículas CSS Animadas de fondo */
        body::before {
            content: "";
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0; left: 0;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 0),
                radial-gradient(rgba(0, 123, 255, 0.15) 2px, transparent 0);
            background-size: 40px 40px, 90px 90px;
            background-position: 0 0, 20px 20px;
            animation: moveBackground 30s linear infinite;
            z-index: 1;
        }

        @keyframes moveBackground {
            0% { background-position: 0 0, 20px 20px; }
            100% { background-position: 40px 40px, 110px 110px; }
        }

        /* === CONTENEDOR GLASSMORPHISM === */
        .dashboard-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        /* Cabecera */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap; /* Permitir envolver en pantallas pequeñas */
            gap: 15px; /* Espacio entre elementos envueltos */
        }

        .logo-section h1 {
            font-size: 24px;
            background: linear-gradient(45deg, #00ffff, #007bff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }

        .logo-section p {
            font-size: 13px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .badge-debian {
            background: rgba(215, 7, 81, 0.2);
            border: 1px solid #d70751;
            color: #ff4d8d;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* === METRICAS EN TIEMPO REAL === */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            border-color: rgba(0, 123, 255, 0.3);
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.05);
        }

        .metric-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            align-items: center; /* Alinear verticalmente los textos */
        }

        .metric-title { font-size: 14px; color: #9ca3af; text-transform: uppercase; letter-spacing: 1px; }
        .metric-value { font-size: 22px; font-weight: bold; color: #ffffff; }

        /* Barra de progreso */
        .progress-bar-bg {
            background: rgba(255, 255, 255, 0.05);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 1s ease-out;
        }

        .cpu-fill { background: linear-gradient(90deg, #00d2ff, #007bff); width: <?php echo $cpu_usage; ?>%; }
        .ram-fill { background: linear-gradient(90deg, #a8ff78, #78ffd6); width: <?php echo $ram_percentage; ?>%; }
        .disk-fill { background: linear-gradient(90deg, #ff9966, #ff5e62); width: <?php echo $disk_percentage; ?>%; }

        .metric-footer {
            font-size: 11px;
            color: #6b7280;
            margin-top: 8px;
            text-align: right;
        }

        /* === SECCIÓN INFERIOR COMPUESTA (Con RESPONSIVIDAD) === */
        .bottom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
        }

        /* Terminal Falsa de Linux */
        .terminal-box {
            background: #07080d;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 20px;
            font-family: 'Courier New', Courier, monospace;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.8);
            min-height: 220px;
        }

        .terminal-header {
            display: flex;
            gap: 6px;
            margin-bottom: 15px;
            border-bottom: 1px solid #1f2937;
            padding-bottom: 8px;
        }

        .dot { width: 11px; height: 11px; border-radius: 50%; }
        .dot-r { background: #ef4444; }
        .dot-y { background: #f59e0b; }
        .dot-g { background: #10b981; }

        .terminal-body {
            color: #10b981;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: auto; /* Permitir scroll horizontal si el texto es muy largo */
        }

        .terminal-prompt { color: #3b82f6; }
        .terminal-cursor {
            display: inline-block;
            width: 8px;
            height: 15px;
            background: #10b981;
            animation: blink 1s infinite;
            vertical-align: middle;
            margin-left: 4px;
        }

        @keyframes blink { 0%, 40% { opacity: 1; } 50%, 90% { opacity: 0; } }

        /* Servicios / Enlaces */
        .services-box {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .services-box h3 {
            font-size: 16px;
            color: #e5e7eb;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .btn-service {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 14px;
            color: #d1d5db;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-align: left; /* Alinear texto a la izquierda */
        }

        .btn-service:hover {
            background: rgba(0, 123, 255, 0.1);
            border-color: #007bff;
            color: #ffffff;
            transform: scale(1.02);
        }

        .btn-service span {
            font-size: 18px;
            flex-shrink: 0; /* No encoger el icono */
        }

        /* === MEDIAS QUERIES PARA RESPONSIVIDAD === */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 25px; /* Reducir padding interno */
            }

            header {
                justify-content: center; /* Centrar elementos en la cabecera */
                text-align: center;
            }

            .logo-section {
                width: 100%; /* Ocupar todo el ancho */
            }

            .badge-debian {
                margin: 0 auto; /* Centrar el badge */
            }

            .metrics-grid {
                grid-template-columns: 1fr; /* Una columna para métricas */
            }

            .bottom-grid {
                grid-template-columns: 1fr; /* Una columna para terminal y servicios */
            }

            .terminal-box {
                min-height: 180px; /* Reducir altura de la terminal */
            }
        }

        @media (max-width: 480px) {
            .services-grid {
                grid-template-columns: 1fr; /* Una columna para servicios en teléfonos */
            }

            .metric-info {
                flex-direction: column; /* Apilar título y valor verticalmente */
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <header>
            <div class="logo-section">
                <h1>MAURI TECH-LAB</h1>
                <p>Uptime: <?php echo trim($uptime); ?> | Kernel: <?php echo trim($kernel); ?></p>
            </div>
            <div class="badge-debian">
                <span>🌀</span> Debian 13 "Trixie"
            </div>
        </header>

        <div class="metrics-grid">
            
            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-title">Procesador</span>
                    <span class="metric-value"><?php echo $cpu_usage; ?> %</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill cpu-fill"></div>
                </div>
                <div class="metric-footer">Sys Load Average</div>
            </div>

            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-title">Memoria RAM</span>
                    <span class="metric-value"><?php echo $ram_percentage; ?> %</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill ram-fill"></div>
                </div>
                <div class="metric-footer"><?php echo $mem_used; ?>MB / <?php echo $mem_total; ?>MB</div>
            </div>

            <div class="metric-card">
                <div class="metric-info">
                    <span class="metric-title">Almacenamiento</span>
                    <span class="metric-value"><?php echo $disk_percentage; ?> %</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill disk-fill"></div>
                </div>
                <div class="metric-footer"><?php echo $disk_used_gb; ?>GB / <?php echo $disk_total_gb; ?>GB</div>
            </div>

        </div>

        <div class="bottom-grid">
            
            <div class="terminal-box">
                <div class="terminal-header">
                    <div class="dot dot-r"></div>
                    <div class="dot dot-y"></div>
                    <div class="dot dot-g"></div>
                </div>
                <div class="terminal-body">
                    <span class="terminal-prompt">kgmauri1993@debianserver:~$</span> <span id="typing-effect"></span><span class="terminal-cursor"></span>
                    <div id="terminal-results" style="margin-top: 10px; color: #a3e635; display: none;">
                        ✓ Apache Web Server Status: RUNNING<br>
                        ✓ PHP Engine: ACTIVE (v<?php echo phpversion(); ?>)<br>
                        ✓ SSH Remote Access: ESTABLISHED (Port 22)<br>
                        ✓ IP Asignada: <?php echo trim($server_ip); ?>
                    </div>
                </div>
            </div>

            <div class="services-box">
                <h3>Servicios del Servidor</h3>
                <div class="services-grid">
                    <a href="https://github.com/klevergj" target="_blank" class="btn-service">
                        <span>🐙</span> GitHub Lab Profile
                    </a>
                    <a href="#" class="btn-service" onclick="alert('Próximamente instalaremos Portainer aquí')">
                        <span>🐳</span> Docker Portainer
                    </a>
                    <a href="#" class="btn-service" onclick="alert('Próximamente configuraremos tu nube privada')">
                        <span>☁️</span> Nextcloud Storage
                    </a>
                    <a href="#" class="btn-service" onclick="alert('Próximamente instalaremos tu base de datos')">
                        <span>🗄️</span> phpMyAdmin DB
                    </a>
                </div>
            </div>

        </div>

    </div>

    <script>
        const textToType = "systemctl status apache2 php-infrastructure.service";
        const typingElement = document.getElementById("typing-effect");
        const resultsElement = document.getElementById("terminal-results");
        let index = 0;

        function typeCommand() {
            if (index < textToType.length) {
                typingElement.innerHTML += textToType.charAt(index);
                index++;
                setTimeout(typeCommand, 70);
            } else {
                setTimeout(() => {
                    resultsElement.style.display = "block";
                }, 500);
            }
        }

        // Iniciar la animación al cargar la página
        window.onload = typeCommand;
    </script>
</body>
</html>
