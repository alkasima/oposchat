<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablece tu contraseña en OposChat</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #6366f1;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #6366f1;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #4f46e5;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">OposChat</div>
    </div>

    <div class="content">
        <div class="greeting">
            Hola {{ $user->name }},
        </div>

        <p>
            Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en OposChat.
        </p>

        <p>
            Para elegir una nueva contraseña, haz clic en el siguiente botón:
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" class="button">
                Restablecer mi contraseña
            </a>
        </div>

        <p style="font-size: 14px; color: #64748b;">
            Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
            <a href="{{ $resetUrl }}" style="color: #6366f1; text-decoration: none;">
                {{ $resetUrl }}
            </a>
        </p>

        <div class="divider"></div>

        <p style="font-size: 14px; color: #64748b;">
            Si no has solicitado restablecer tu contraseña, puedes ignorar este correo. Tu contraseña actual seguirá siendo válida.
        </p>
    </div>

    <div class="footer">
        <p>
            Atentamente,<br>
            El equipo de OposChat<br>
            <a href="https://www.oposchat.com" style="color: #6366f1;">www.oposchat.com</a>
        </p>
    </div>
</body>
</html>
