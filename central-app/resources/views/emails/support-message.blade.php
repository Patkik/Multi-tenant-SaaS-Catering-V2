<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;background:#f6f4ef;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:680px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e7e0d6;border-radius:20px;padding:24px;">
            <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;">{{ $source === 'central' ? 'Central Platform' : 'Tenant Workspace' }} Support</p>
            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;color:#111827;">{{ $subjectLine }}</h1>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tbody>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;width:180px;vertical-align:top;">Category</td>
                        <td style="padding:6px 0;">{{ ucfirst((string) ($payload['category'] ?? 'support')) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">Workspace</td>
                        <td style="padding:6px 0;">
                            {{ (string) ($metadata['workspace_name'] ?? $payload['workspace_name'] ?? 'Unknown workspace') }}
                            @if (! empty($metadata['workspace_id'] ?? $payload['workspace_id'] ?? null))
                                <span style="color:#6b7280;">(#{{ $metadata['workspace_id'] ?? $payload['workspace_id'] }})</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">Contact</td>
                        <td style="padding:6px 0;">
                            {{ (string) ($metadata['contact_name'] ?? $payload['contact_name'] ?? 'Unknown user') }}
                            @if (! empty($metadata['contact_email'] ?? $payload['contact_email'] ?? null))
                                &lt;{{ $metadata['contact_email'] ?? $payload['contact_email'] }}&gt;
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">Role</td>
                        <td style="padding:6px 0;">{{ (string) ($metadata['user_role'] ?? $payload['user_role'] ?? 'Unknown') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">Page</td>
                        <td style="padding:6px 0;">{{ (string) ($metadata['page_path'] ?? $payload['page_path'] ?? 'Unknown') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">App version</td>
                        <td style="padding:6px 0;">{{ (string) ($metadata['app_version'] ?? $payload['app_version'] ?? 'Unknown') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;font-weight:700;vertical-align:top;">IP / Agent</td>
                        <td style="padding:6px 0;">
                            {{ (string) ($metadata['request_ip'] ?? 'Unknown IP') }}
                            @if (! empty($metadata['user_agent'] ?? null))
                                <div style="margin-top:4px;color:#6b7280;word-break:break-word;">{{ $metadata['user_agent'] }}</div>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin:24px 0 8px;font-size:16px;">Message</h2>
            <div style="white-space:pre-wrap;line-height:1.6;background:#f9fafb;border:1px solid #e5e7eb;border-radius:16px;padding:16px;">{{ (string) ($payload['message'] ?? '') }}</div>
        </div>
    </div>
</body>
</html>
