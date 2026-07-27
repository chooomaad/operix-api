<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de connexion Operix</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                <!-- Header -->
                <tr>
                    <td style="background:#0f2847;padding:32px 40px;text-align:center;">
                        <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;letter-spacing:1px;">
                            OPERIX
                        </h1>
                        <p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">
                            Plateforme HSE & Opérations
                        </p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:40px;">
                        <p style="margin:0 0 8px;color:#374151;font-size:16px;">
                            Bonjour <strong>{{ $name }}</strong>,
                        </p>
                        <p style="margin:0 0 32px;color:#6b7280;font-size:14px;line-height:1.6;">
                            Voici votre code de connexion à la plateforme Operix.
                            Ce code est valable <strong>5 minutes</strong> et ne peut être utilisé qu'une seule fois.
                        </p>

                        <!-- OTP Code -->
                        <div style="background:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;padding:32px;text-align:center;margin-bottom:32px;">
                            <p style="margin:0 0 8px;color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:2px;">
                                Votre code
                            </p>
                            <p style="margin:0;color:#0f2847;font-size:48px;font-weight:800;letter-spacing:16px;">
                                {{ $code }}
                            </p>
                        </div>

                        <p style="margin:0 0 8px;color:#6b7280;font-size:13px;line-height:1.6;">
                            Si vous n'avez pas demandé ce code, ignorez cet email.
                            Votre compte reste sécurisé.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f8fafc;padding:24px 40px;border-top:1px solid #e2e8f0;">
                        <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                            © {{ date('Y') }} Operix Platform — operix-app.com<br>
                            Ce message est confidentiel et destiné uniquement à son destinataire.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
