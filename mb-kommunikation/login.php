<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if ($auth->isLoggedIn()) {
    redirect('/admin/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Ungültige Anfrage. Bitte versuchen Sie es erneut.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Bitte füllen Sie alle Felder aus.';
        } else {
            $result = $auth->login($email, $password);
            if ($result === true) {
                redirect('/admin/');
            } else {
                $error = 'Ungültige E-Mail-Adresse oder Passwort.';
            }
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – mb Kommunikation + Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1e3a5f', 600: '#162d4a', 50: '#e8eef6' },
                        accent: { DEFAULT: '#e94560', 600: '#d43858' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-primary to-slate-800 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="inline-block">
                <div class="text-white">
                    <div class="text-3xl font-bold tracking-tight">mb</div>
                    <div class="text-sm font-light tracking-widest uppercase text-white/70">Kommunikation + Events</div>
                </div>
            </a>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Willkommen zurück</h1>
            <p class="text-gray-500 mb-6">Melden Sie sich in Ihrem Konto an</p>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-6 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login.php">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">E-Mail-Adresse</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="email"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-all"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="ihre@email.ch"
                    >
                </div>

                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Passwort</label>
                    <div class="relative">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary transition-all pr-12"
                            placeholder="••••••••"
                        >
                        <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full bg-primary hover:bg-primary-600 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 hover:shadow-lg active:scale-95"
                >
                    Anmelden
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-100 text-center">
                <a href="/" class="text-sm text-gray-500 hover:text-primary transition-colors">← Zurück zur Website</a>
            </div>
        </div>

        <p class="text-center text-white/50 text-xs mt-6">
            © <?= date('Y') ?> mb Kommunikation + Events – Alle Rechte vorbehalten
        </p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
