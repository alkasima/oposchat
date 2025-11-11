<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Correo verificado con éxito! - OposChat</title>
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
        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-6">
            <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        
        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 mb-4">
            ¡Correo verificado con éxito!
        </h1>
        
        <!-- Message -->
        <p class="text-gray-600 mb-8">
            Tu correo ha sido verificado. Ahora puedes empezar a usar OposChat.
        </p>
        
        <!-- Go to Home Button -->
        <a href="{{ route('dashboard') }}" 
           class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
            Ir al inicio
        </a>
        
        <!-- Additional info -->
        <p class="text-sm text-gray-500 mt-6">
            ¡Bienvenido a OposChat! Explora todas nuestras funciones de preparación para exámenes.
        </p>
    </div>
</body>
</html>