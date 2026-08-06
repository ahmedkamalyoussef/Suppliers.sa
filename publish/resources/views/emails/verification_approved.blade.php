<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Verification Approved - {{ $appName }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #F9FAFB;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1F2937;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .wrapper {
            width: 100%;
            max-width: 100%;
            background-color: #F9FAFB;
            padding: 24px 12px;
            overflow-x: hidden;
        }
        .container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            background-color: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 32px 20px;
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .brand-text {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #10B981;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 14px;
            line-height: 1.35;
        }
        .message {
            font-size: 15px;
            line-height: 1.6;
            color: #4B5563;
            margin: 0 0 24px;
        }
        .cta-button {
            display: inline-block;
            background-color: #10B981;
            color: #FFFFFF !important;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            padding: 12px 26px;
            border-radius: 8px;
            max-width: 100%;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #F3F4F6;
            font-size: 12px;
            color: #9CA3AF;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <!-- Hidden Preheader -->
    <div style="display:none; font-size:1px; color:#F9FAFB; line-height:1px; max-height:0px; max-width:0px; opacity:0; overflow:hidden; mso-hide:all;">
        Your business verification has been approved. Account status: Verified. Ref: {{ $refCode ?? uniqid() }}
    </div>

    <div class="wrapper">
        <div class="container">
            <!-- Logo Image -->
            <div style="text-align: center; margin-bottom: 16px;">
                @if (file_exists(public_path('images/logo.png')) && isset($message))
                    <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="{{ $appName }} Logo" width="56" height="56" style="display: block; margin: 0 auto; border: 0; outline: none; text-decoration: none;" />
                @else
                    <img src="https://supplier.sa/images/logo.png" alt="{{ $appName }} Logo" width="56" height="56" style="display: block; margin: 0 auto; border: 0; outline: none; text-decoration: none;" />
                @endif
            </div>

            <!-- Brand Name -->
            <div class="brand-text">{{ $appName }}</div>

            <!-- Heading -->
            <h1 class="title">Business Verification Approved</h1>

            <!-- Message -->
            <p class="message">
                Hello {{ $supplierName }},<br><br>
                Your business verification has been successfully approved. Your account is now fully verified on <strong>{{ $appName }}</strong>. You can now access all verified business features and benefits.
            </p>

            <!-- CTA Button -->
            <div>
                <a href="https://supplier.sa/" class="cta-button">Open {{ $appName }}</a>
            </div>

            <!-- Footer -->
            <div class="footer">
                Thank you for choosing {{ $appName }}.<br>
                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
