<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enlace de verificación inválido - OposChat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                    }
                }
            }
        }
    </script>
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
</head>
<body class="bg-gradient-to-br from-primary to-secondary min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-xl p-8 text-center">
        <!-- Error Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
            <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>
        
        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 mb-4">
            Este enlace ha caducado o no es válido
        </h1>
        
        <!-- Message -->
        <p class="text-gray-600 mb-8">
            {{ $error }}
        </p>
        
        <!-- Resend Email Form -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                Solicitar nuevo enlace de verificación
            </h3>
            
            <form action="{{ route('email.verify.resend') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Tu correo electrónico"
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-primary transition-colors duration-200"
                    >
                </div>
                <button 
                    type="submit"
                    class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200"
                >
                    Solicitar nuevo enlace
                </button>
            </form>
        </div>
        
        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="mb-4 p-4 rounded-md bg-green-50 border border-green-200">
                <div class="text-sm text-green-700">
                    {{ session('success') }}
                </div>
            </div>
        @endif
        
        @if (session('error'))
            <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200">
                <div class="text-sm text-red-700">
                    {{ session('error') }}
                </div>
            </div>
        @endif
        
        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="mb-4 p-4 rounded-md bg-red-50 border border-red-200">
                <div class="text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif
        
        <!-- Login Link -->
        <div class="pt-4 border-t border-gray-200">
            <p class="text-sm text-gray-500">
                ¿Ya tienes una cuenta verificada? 
                <a href="{{ route('login') }}" class="text-primary hover:text-primary/90 font-medium">
                    Iniciar sesión
                </a>
            </p>
        </div>
    </div>
</body>
</html>