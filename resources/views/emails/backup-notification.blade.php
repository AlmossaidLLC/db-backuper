<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup Notification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #4F46E5;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .status-success {
            color: #10B981;
            font-weight: bold;
        }
        .status-failed {
            color: #EF4444;
            font-weight: bold;
        }
        .info-box {
            background: white;
            border-left: 4px solid #4F46E5;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .error-box {
            background: #FEF2F2;
            border-left: 4px solid #EF4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #991B1B;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Database Backup Notification</h1>
    </div>

    <div class="content">
        @if($backup->status === 'completed')
            <p class="status-success">✓ Backup Completed Successfully</p>

            <div class="info-box">
                <strong>Connection:</strong> {{ $backup->connection->label }}<br>
                <strong>Database:</strong> {{ $backup->connection->db }}<br>
                <strong>File Name:</strong> {{ $backup->file_name }}<br>
                <strong>File Size:</strong> {{ $backup->file_size_human }}<br>
                <strong>Completed At:</strong> {{ $backup->completed_at->format('Y-m-d H:i:s') }}
            </div>

            <p>You can download the backup file using the link below:</p>

            <a href="{{ route('backups.download', $backup->id) }}" class="button">Download Backup</a>

            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                <strong>Note:</strong> This download link will remain valid. Please save the backup file to a secure location.
            </p>
        @else
            <p class="status-failed">✗ Backup Failed</p>

            <div class="info-box">
                <strong>Connection:</strong> {{ $backup->connection->label }}<br>
                <strong>Database:</strong> {{ $backup->connection->db }}<br>
                <strong>Failed At:</strong> {{ $backup->updated_at->format('Y-m-d H:i:s') }}
            </div>

            @if($backup->error_message || $errorMessage)
                <div class="error-box">
                    <strong>Error Message:</strong><br>
                    {{ $backup->error_message ?? $errorMessage }}
                </div>
            @endif

            <p>Please check your connection settings and try again.</p>
        @endif
    </div>
</body>
</html>

