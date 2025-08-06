
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Sustainity</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    @vite(['resources/css/login.css'])
    @vite('resources/js/script.js')
</head>
<body>
<header class="navbar">
    <div class="navbar-left">
        <a href="#">
            <img src="{{ asset('img/DevPlay logo.png') }}" alt="Logo" class="logo-image">
        </a>
    </div>
    <nav class="navbar-center nav-links">
        <a href="{{ route('rutaInicio') }}">Inicio</a>
        <a href="/donar">Donativos</a>
        <a href="{{ route('rutaNosotros') }}">Nosotros</a>
    </nav>

    <div class="navbar-right auth-buttons">
        @if (session('logged_in'))
            <div class="user-info">
                <span class="username" onclick="toggleLogoutDropdown()">
                    {{ session('username') }}
                </span>
                <div id="logoutDropdown" class="logout-dropdown">
                    <button onclick="window.location.href='{{ route('rutaLogout') }}'">Cerrar sesión</button>
                </div>
            </div>
        @else
            <button class="login-btn" onclick="window.location.href='{{ route('rutaLogin') }}'">Iniciar Sesión</button>
        @endif
        <button class="news-btn" onclick="window.location.href='{{ route('rutaNoticias') }}'">Noticias</button>
    </div>
    
    <script>
        function toggleLogoutDropdown() {
            const dropdown = document.getElementById('logoutDropdown');
            dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        }
        // Opcional: ocultar el dropdown al hacer click fuera
        document.addEventListener('click', function(e) {
            const userInfo = document.querySelector('.user-info');
            if(userInfo && !userInfo.contains(e.target)) {
                document.getElementById('logoutDropdown').style.display = 'none';
            }
        });
    </script>
</header>

@if(session('message'))
    <div id="alerta_tiempo" class="alert" 
         style="display: none; width: 100%; padding: 15px 0; position: fixed; top: -100px; left: 0; z-index: 1000; background-color: #d4edda; color: #155724; text-align: center;">
        {{ session('message') }}
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const alerta = document.getElementById("alerta_tiempo");
            alerta.style.display = "block";
            setTimeout(() => {
                alerta.style.transition = "top 0.5s ease";
                alerta.style.top = "80px";
            }, 100);
            setTimeout(() => {
                alerta.style.top = "-100px";
            }, 5000);
        });
    </script>
@endif

@if(session('error'))
    <div id="alerta_error" class="alert" 
         style="display: none; width: 100%; padding: 15px 0; position: fixed; top: -100px; left: 0; z-index: 1000; background-color: #f8d7da; color: #721c24; text-align: center;">
        {{ session('error') }}
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const alerta = document.getElementById("alerta_error");
            alerta.style.display = "block";
            setTimeout(() => {
                alerta.style.transition = "top 0.5s ease";
                alerta.style.top = "80px";
            }, 100);
            setTimeout(() => {
                alerta.style.top = "-100px";
            }, 5000);
        });
    </script>
@endif

<!-- Modal de carga -->
<div id="loadingModal" style="display:none; position: fixed; top:0; left:0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background: #fff; padding: 20px; border-radius: 5px; font-family: 'Press Start 2P', cursive; text-align: center;">
        <p class="loading-text">Cargando...</p>
    </div>
</div>

<div class="main-card">
    <div class="text-content">
        <h1>Crea tu Cuenta</h1>
        <!-- Contenedor para mensajes de error de registro -->
        <div id="registerError" style="color:#ff4d4d; font-size:0.8em; margin-bottom:10px; display:none;"></div>
        
        <form id="registerForm" action="{{ route('rutaCrearCuenta') }}" method="POST" class="donation-form">
            @csrf
            
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" placeholder="Correo Electrónico" value="{{ old('email') }}" required>
            <small class="text-danger fst-italic">{{ $errors->first('email') }}</small>
            
            <label for="username">Nombre de Usuario</label>
            <input type="text" id="username" name="username" placeholder="Nombre de Usuario" value="{{ old('username') }}" required>
            <small class="text-danger fst-italic">{{ $errors->first('username') }}</small>
            
            <label for="password">Contraseña</label>
            <div class="password-field-container">
                <input type="password" id="password" name="password" placeholder="Contraseña" required>
                <button type="button" id="togglePassword" class="password-toggle">Ver</button>
            </div>
            <small class="text-danger fst-italic">{{ $errors->first('password') }}</small>
            
            <!-- Medidor de fuerza de contraseña -->
            <div id="passwordStrengthMeter" class="password-strength-meter">
                <div id="passwordStrengthBar" class="password-strength-meter-bar"></div>
            </div>
            <div id="passwordFeedback" class="password-feedback"></div>
            
            <label for="confirm_password">Confirmar Contraseña</label>
            <div class="password-field-container">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirmar Contraseña" required>
                <button type="button" id="toggleConfirmPassword" class="password-toggle">Ver</button>
            </div>
            <small class="text-danger fst-italic">{{ $errors->first('confirm_password') }}</small>
            
            <button type="submit" class="play-btn">Crear Cuenta</button>
        </form>
        
        <p><a href="{{ route('rutaLogin') }}" class="create-account-link">¿Ya tienes cuenta?</a></p>
    </div>
    
    <img src="{{ asset('img/character.png') }}" alt="Game Character" class="character-image">
</div>

<footer class="footer">
    <div class="footer-content">
        <a href="#">Política de Privacidad</a> | 
        <a href="#">Términos y Condiciones</a> | 
        <a href="#">Contacto</a>
    </div>
    <p>&copy; 2024 Sustainity. Todos los derechos reservados.</p>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const registerForm = document.getElementById('registerForm');
        const loadingModal = document.getElementById('loadingModal');
        
        if (registerForm) {
            registerForm.addEventListener('submit', function(event) {
                // Mostrar el modal de carga
                loadingModal.style.display = 'flex';
            });
        }
        
        // Toggle para contraseña
        const passwordInput = document.getElementById('password');
        const togglePasswordButton = document.getElementById('togglePassword');
        
        if (togglePasswordButton) {
            togglePasswordButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                togglePasswordButton.textContent = type === 'password' ? 'Ver' : 'Ocultar';
            });
        }
        
        // Toggle para confirmar contraseña
        const confirmPasswordInput = document.getElementById('confirm_password');
        const toggleConfirmPasswordButton = document.getElementById('toggleConfirmPassword');
        
        if (toggleConfirmPasswordButton) {
            toggleConfirmPasswordButton.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                toggleConfirmPasswordButton.textContent = type === 'password' ? 'Ver' : 'Ocultar';
            });
        }
        
        // Medidor de fuerza de contraseña
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthMeter = document.getElementById('passwordStrengthMeter');
                const strengthBar = document.getElementById('passwordStrengthBar');
                const feedback = document.getElementById('passwordFeedback');
                
                if (password.length > 0) {
                    strengthMeter.style.display = 'block';
                    feedback.style.display = 'block';
                    
                    const strength = calculatePasswordStrength(password);
                    updatePasswordStrength(strength, strengthBar, feedback);
                } else {
                    strengthMeter.style.display = 'none';
                    feedback.style.display = 'none';
                }
            });
        }
        
        function calculatePasswordStrength(password) {
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) score += 1;
            else feedback.push("Al menos 8 caracteres");
            
            if (/[a-z]/.test(password)) score += 1;
            else feedback.push("Minúsculas");
            
            if (/[A-Z]/.test(password)) score += 1;
            else feedback.push("Mayúsculas");
            
            if (/[0-9]/.test(password)) score += 1;
            else feedback.push("Números");
            
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            else feedback.push("Símbolos");
            
            return { score, feedback };
        }
        
        function updatePasswordStrength(strength, bar, feedback) {
            const { score, feedback: feedbackArray } = strength;
            
            // Limpiar clases anteriores
            bar.className = 'password-strength-meter-bar';
            
            if (score <= 1) {
                bar.classList.add('strength-weak');
                feedback.textContent = 'Débil: ' + feedbackArray.join(', ');
                feedback.style.color = '#ff4d4d';
            } else if (score === 2) {
                bar.classList.add('strength-medium');
                feedback.textContent = 'Medio: ' + feedbackArray.join(', ');
                feedback.style.color = '#ffa500';
            } else if (score === 3 || score === 4) {
                bar.classList.add('strength-good');
                feedback.textContent = 'Buena';
                feedback.style.color = '#ffcb05';
            } else {
                bar.classList.add('strength-strong');
                feedback.textContent = 'Excelente';
                feedback.style.color = '#4CAF50';
            }
        }
        
        // Validación de coincidencia de contraseñas
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length > 0) {
                    if (password !== confirmPassword) {
                        this.style.borderColor = '#ff4d4d';
                    } else {
                        this.style.borderColor = '#4CAF50';
                    }
                } else {
                    this.style.borderColor = '#ffcb05';
                }
            });
        }
    });
</script>
</body>
</html>