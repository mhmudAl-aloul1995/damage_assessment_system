<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome Message</title>
</head>

<body style="margin:0; padding:0; font-family:Arial, sans-serif; background-color:#f4f6f9;">

<table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f4f6f9; padding:20px;">
    <tr>
        <td align="center">

            <table width="600" border="0" cellspacing="0" cellpadding="0"
                   style="background-color:#ffffff; border:1px solid #e5e5e5; border-collapse:collapse;">

                {{-- Header --}}
                <tr>
                    <td align="center" style="padding:30px; background-color:#004a99;">

                        <img src="{{ $message->embed(public_path('logo-email.jpg')) }}"
                             alt="Damage Assessment Project"
                             width="220"
                             style="display:block; border:0; outline:none; text-decoration:none; margin:0 auto 15px auto;">

                        <div style="color:#ffffff; font-size:24px; font-weight:bold; letter-spacing:1px;">
                            Damage Assessment Project
                        </div>

                    </td>
                </tr>

                {{-- Content --}}
                <tr>
                    <td style="padding:35px; color:#333333; font-size:15px; line-height:1.7;">

                        <p style="font-size:18px; margin:0 0 20px 0;">
                            Hello,
                        </p>

                        <p style="margin:0 0 20px 0;">
                            Welcome to the team! As part of the
                            <strong>Damage Assessment Project</strong>, we are committed to accurate data
                            management and impactful reporting. I am here to support you with any
                            database-related needs or technical onboarding.
                        </p>

                        {{-- Credentials Box --}}
                        <table width="100%" border="0" cellspacing="0" cellpadding="0"
                               style="background-color:#f0f7ff; border-left:4px solid #004a99; margin:20px 0;">
                            <tr>
                                <td style="padding:15px;">
                                    <p style="margin:0 0 12px 0; font-weight:bold;">
                                        Your account credentials are:
                                    </p>

                                    <p style="margin:0 0 8px 0; font-size:16px;">
                                        <strong>Username:</strong>
                                        <a href="mailto:{{ $username }}" style="color:#0057d9; text-decoration:underline;">
                                            {{ $username }}
                                        </a>
                                    </p>

                                    <p style="margin:0; font-size:16px;">
                                        <strong>Password:</strong> {{ $password }}
                                    </p>
                                </td>
                            </tr>
                        </table>

                        {{-- Button --}}
                        <table border="0" cellspacing="0" cellpadding="0" style="margin:25px 0;">
                            <tr>
                                <td align="center" bgcolor="#004a99" style="border-radius:5px;">
                                    <a href="{{ url('damageAssessment') }}"
                                       target="_blank"
                                       style="font-size:16px; font-family:Arial, sans-serif; color:#ffffff; text-decoration:none; padding:13px 28px; display:inline-block; font-weight:bold; border-radius:5px;">
                                        Login to Dashboard
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px 0;">
                            Please let me know if you have any questions as we begin this collaboration.
                        </p>

                        <p style="margin:0 0 25px 0;">
                            Best regards,
                        </p>

                        {{-- Signature --}}
                        <table width="100%" border="0" cellspacing="0" cellpadding="0"
                               style="border-top:1px solid #dddddd; padding-top:20px;">
                            <tr>
                                <td width="4" style="background-color:#004a99;"></td>

                                <td style="padding-left:15px; font-family:Arial, sans-serif;">
                                    <div style="font-size:16px; font-weight:bold; color:#004a99;">
                                        Eng. Mahmoud Osama Al-Aloul
                                    </div>

                                    <div style="font-size:13px; color:#555555; font-weight:bold; margin-top:3px;">
                                        Database Officer | Damage Assessment Project
                                    </div>

                                    <div style="font-size:12px; color:#777777; margin-top:6px;">
                                        <a href="mailto:mhmudaloul@gmail.com"
                                           style="color:#004a99; text-decoration:none;">
                                            mhmudaloul@gmail.com
                                        </a>
                                    </div>
                                </td>

                                <td align="right" valign="middle" width="140">
                                    <img src="{{ $message->embed(public_path('logo-email.jpg')) }}"
                                         alt="Project Icon"
                                         width="120"
                                         style="display:block; border:0; outline:none; text-decoration:none;">
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>