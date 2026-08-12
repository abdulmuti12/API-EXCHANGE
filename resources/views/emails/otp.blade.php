<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: #1a1a2e; color: #fff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .body { padding: 32px 24px; }
        .greeting { font-size: 16px; color: #333; margin-bottom: 16px; }
        .message { font-size: 14px; color: #666; line-height: 1.6; margin-bottom: 24px; }
        .otp-box { background: #f0f0f0; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 24px; }
        .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #1a1a2e; }
        .expiry { font-size: 12px; color: #999; margin-top: 8px; }
        .footer { background: #fafafa; padding: 16px 24px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Exchange Product</h1>
        </div>
        <div class="body">
            <p class="greeting">Halo {{ $userName }},</p>
            <p class="message">
                Anda menerima email ini karena kami menerima permintaan reset password untuk akun Anda.
                Masukkan kode OTP berikut untuk melanjutkan:
            </p>
            <div class="otp-box">
                <div class="otp-code">{{ $otp }}</div>
                <div class="expiry">Kode ini berlaku selama 10 menit</div>
            </div>
            <p class="message">
                Jika Anda tidak meminta reset password, abaikan email ini.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Exchange Product. All rights reserved.
        </div>
    </div>
</body>
</html>
