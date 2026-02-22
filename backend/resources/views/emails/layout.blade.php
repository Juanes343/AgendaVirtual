<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <style>
        body { background-color: #f4f4f7; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; }
        .container { display: block; max-width: 600px; margin: 0 auto !important; clear: both; }
        .content { max-width: 600px; margin: 0 auto; display: block; padding: 20px; }
        .main { background-color: #ffffff; border-radius: 8px; border: 1px solid #e9eaed; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        .header { background-color: #0F3460; padding: 20px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: bold; }
        .wrapper { padding: 30px; box-sizing: border-box; }
        .footer { clear: both; margin-top: 10px; text-align: center; width: 100%; }
        .footer td, .footer p, .footer span, .footer a { color: #999999; font-size: 12px; text-align: center; }
        .btn-primary { background-color: #3b82f6; border: solid 1px #3b82f6; border-radius: 5px; box-sizing: border-box; color: #ffffff; cursor: pointer; display: inline-block; font-size: 14px; font-weight: bold; margin: 0; padding: 12px 25px; text-decoration: none; text-transform: capitalize; }
        .btn-primary:hover { background-color: #2563eb; border-color: #2563eb; }
        p { margin-bottom: 15px; color: #333333; }
    </style>
</head>
<body>
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="body" width="100%">
        <tr>
            <td>&nbsp;</td>
            <td class="container">
                <div class="content">
                    <!-- START CARD -->
                    <div class="main">
                        <!-- HEADER -->
                        <div class="header">
                            <h1>{{ env('APP_DISPLAY_NAME', 'SanDi•Med') }}</h1>
                        </div>
                        
                        <!-- CONTENT -->
                        <div class="wrapper">
                            @yield('content')
                        </div>
                    </div>
                    <!-- END CARD -->

                    <!-- FOOTER -->
                    <div class="footer">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                            <tr>
                                <td class="content-block">
                                    <span class="apple-link">{{ env('APP_DISPLAY_NAME', 'SanDi•Med') }} Portal del Paciente</span>
                                    <br> No respondas a este correo electrónico.
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
            <td>&nbsp;</td>
        </tr>
    </table>
</body>
</html>