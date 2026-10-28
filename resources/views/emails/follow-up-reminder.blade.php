<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — Shadi Events</title>
    <style>
        @media only screen and (max-width: 600px) {
            .outer { padding: 16px 8px !important; }
            .content { padding: 24px 20px !important; }
            .detail-cell { display: block !important; width: auto !important; padding: 10px 16px !important; }
            .headline { font-size: 29px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#FAF7F2;color:#3A2E2A;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;font-size:1px;color:#FAF7F2;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $heading }}: {{ $coupleName ?: $clientName }}. Scheduled {{ $dueAt }}.
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAF7F2;">
        <tr><td class="outer" align="center" style="padding:32px 16px;">
            <!--[if mso]><table role="presentation" width="640" align="center"><tr><td><![endif]-->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;background-color:#FFFCF7;border:1px solid #E5D8C6;border-radius:12px;">
                <tr><td align="center" style="padding:28px 24px;border-top:5px solid #6B2A38;border-bottom:1px solid #E5D8C6;">
                    <img src="{{ $logoUrl }}" width="208" alt="Shadi Events" style="display:block;width:208px;max-width:100%;height:auto;border:0;color:#6B2A38;font-family:Georgia,serif;font-size:26px;">
                    <p style="margin:14px 0 0;color:#9B805E;font-size:11px;letter-spacing:2px;">CLIENT RELATIONSHIPS · FOLLOW-UP REMINDER</p>
                </td></tr>
                <tr><td class="content" style="padding:32px;">
                    <span style="display:inline-block;background-color:#F1E6E6;color:#6B2A38;border-radius:20px;padding:6px 12px;font-size:12px;font-weight:bold;">{{ $overdue ? 'OVERDUE' : 'DUE NOW' }}</span>
                    <h1 class="headline" style="margin:16px 0 20px;font-family:Georgia,'Times New Roman',serif;font-size:34px;line-height:1.2;font-weight:normal;color:#3F1A24;">{{ $heading }}</h1>
                    <p style="margin:0 0 12px;font-size:15px;line-height:1.7;">Hello {{ $recipientName }},</p>
                    <p style="margin:0 0 24px;font-size:15px;line-height:1.7;overflow-wrap:anywhere;word-break:break-word;">Please follow up with <strong>{{ $clientName }}</strong>@if ($coupleName) regarding <strong>{{ $coupleName }}</strong>{{ "'s wedding" }}@endif{{ '.' }}</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F2EBE2;border-left:4px solid #B69E7C;">
                        <tr><td style="padding:20px;overflow-wrap:anywhere;word-break:break-word;">
                            <h2 style="margin:0 0 16px;color:#3F1A24;font-family:Georgia,serif;font-size:23px;font-weight:normal;">The follow-up</h2>
                            <p style="margin:0 0 5px;color:#716762;font-size:12px;">SCHEDULED FOR</p>
                            <p style="margin:0 0 16px;color:#6B2A38;font-size:17px;font-weight:bold;">{{ $dueAt }}</p>
                            <p style="margin:0 0 5px;color:#716762;font-size:12px;">RESPONSIBLE MEMBER</p>
                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;">{{ $recipientName }}</p>
                            <p style="margin:0 0 5px;color:#716762;font-size:12px;">PURPOSE / NOTES</p>
                            <p style="margin:0;font-size:14px;line-height:1.7;">{!! nl2br(e($notes)) !!}</p>
                        </td></tr>
                    </table>
                    <h2 style="margin:28px 0 16px;font-family:Georgia,serif;font-size:25px;font-weight:normal;color:#3F1A24;">The lead details</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;border:1px solid #E5D8C6;border-radius:8px;">
                        @foreach (array_chunk($details, 2, true) as $row)
                            <tr>
                                @foreach ($row as $label => $value)
                                    <td class="detail-cell" width="50%" valign="top" style="padding:14px 16px;overflow-wrap:anywhere;word-break:break-word;">
                                        <p style="margin:0 0 6px;font-size:12px;line-height:1.5;color:#716762;">{{ $label }}</p>
                                        <p style="margin:0;font-size:14px;line-height:1.6;color:#3A2E2A;">
                                            @if ($label === 'Phone' && $phoneUrl)
                                                <a href="{{ $phoneUrl }}" style="color:#6B2A38;text-decoration:underline;">{{ $value }}</a>
                                            @elseif ($label === 'Email' && $emailUrl)
                                                <a href="{{ $emailUrl }}" style="color:#6B2A38;text-decoration:underline;">{{ $value }}</a>
                                            @else
                                                {{ $value ?: 'Not provided' }}
                                            @endif
                                        </p>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:28px auto 20px;">
                        <tr><td align="center" bgcolor="#6B2A38" style="background-color:#6B2A38;border-radius:6px;mso-padding-alt:16px 28px;">
                            <a href="{{ $leadUrl }}" style="display:inline-block;padding:16px 28px;color:#FFFFFF;font-size:15px;font-weight:bold;text-decoration:none;">Open lead in CRM</a>
                        </td></tr>
                    </table>
                    <p style="margin:0 0 6px;text-align:center;font-size:12px;line-height:1.6;color:#716762;">You can also open the lead using this link:</p>
                    <p style="margin:0;text-align:center;font-size:12px;line-height:1.6;overflow-wrap:anywhere;word-break:break-all;"><a href="{{ $leadUrl }}" style="color:#6B2A38;text-decoration:underline;">{{ $leadUrl }}</a></p>
                </td></tr>
                <tr><td style="padding:22px 28px;background-color:#F2EBE2;border-top:1px solid #E5D8C6;text-align:center;">
                    <p style="margin:0 0 8px;font-size:12px;line-height:1.7;color:#716762;">Complete or cancel this follow-up in the CRM to stop reminders. Pending follow-ups are reminded daily at 10 AM IST while the lead remains open.</p>
                    <p style="margin:0;font-family:Georgia,serif;font-size:17px;color:#6B2A38;">Shadi Events CRM</p>
                </td></tr>
            </table>
            <!--[if mso]></td></tr></table><![endif]-->
        </td></tr>
    </table>
</body>
</html>
