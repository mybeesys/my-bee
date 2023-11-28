
<html><head>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style type="text/css">
        body {
            background-color: #88BDBF;
            margin: 0px;
        }
    </style></head>


<body>
<table border="0" width="50%" style="margin:auto;padding:30px;background-color: #F3F3F3;border:1px solid #009988;">

    <tbody><tr><td style="text-align: center;">

            <img src="{{ $logo ?? "" }}" height="80">

        </td>

    </tr><tr>
        <td>
            <table border="0" cellpadding="0" cellspacing="0" style="text-align:center;width:100%;background-color: #fff;">
                <tbody><tr>
                    <td style="background-color:#009988;height:100px;font-size:50px;color:#fff;"><i class="fa fa-envelope-o" aria-hidden="true"></i></td>
                </tr>
                <tr>
                    <td>
                        <h1 style="padding-top:25px;">{{ app()->getLocale() == "en" ? "Email Confirmation" : "التحقق من البريد الإلكتروني" }}</h1>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p style="padding:0px 100px;">
                            {{ __('messages.welcome_to_nour') }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <a href="{{ $link }}" style="display:block;text-decoration:none;width:auto;width:150px;margin:10px auto;border-radius:4px;padding:10px 20px;border: 0;color:#fff;background-color:#009988; ">
                            {{ app()->getLocale() == "en" ? "Verify email address" : "إكمال التحقق" }}
                        </a>
                    </td>
                </tr>
                </tbody></table>
        </td>
    </tr>
    <tr>
        <td>
            <table border="0" width="100%" style="border-radius: 5px;text-align: center;">
                <tbody>
                <tr>
                    <td>
                        <div style="margin-top: 20px;">
                            <span style="font-size:12px;"></span><br>
                            <span style="font-size:12px;">Copyright ©<script>document.write(new Date().getFullYear())</script>{{ now()->year }} {{ config('app.name') }}</span>
                        </div>
                    </td>
                </tr>
                </tbody></table>
        </td>
    </tr>
    </tbody></table>


</body></html>
