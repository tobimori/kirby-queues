---
title: Workers
---

Queue workers are the heart of your background processing system. They continuously monitor your queues, pick up new jobs, and execute them reliably. This guide will show you how to run workers during development and, more importantly, how to set them up for production environments where they need to run 24/7.

## Kirby CLI Setup

Make sure you have the Kirby CLI installed as shown in the [installation guide](0_installation.md). For production daemon setups, you'll need to know the full path to your Kirby executable:

```bash
# Find the path to your Kirby CLI
which kirby  # Global installation
# or
ls vendor/bin/kirby  # Local installation
```

## Running Workers

### Development

During development, you can run a worker directly from your terminal:

```bash
kirby queues:work
```

This starts a worker that will process jobs from all queues. You can also specify which queue to process:

```bash
kirby queues:work --queue=high
kirby queues:work --queue=emails,exports
```

### Worker Options

The worker command accepts several options to control its behavior:

| Option | Description | Example |
| --- | --- | --- |
| `--queue` | Comma-separated list of queues to process | `--queue=high,default` |
| `--sleep` | Seconds to sleep when no jobs are available | `--sleep=3` |
| `--timeout` | Maximum seconds a job can run | `--timeout=90` |
| `--tries` | Maximum attempts before marking a job as failed | `--tries=5` |
| `--memory` | Memory limit in MB | `--memory=256` |
| `--stop-when-empty` | Exit when the queue is empty | `--stop-when-empty` |

### Production Setup

In production, you need workers to run continuously, restart on failure, and start automatically when your server boots. The two most common approaches are using supervisord or systemd.

## Supervisord Setup

Supervisord is a popular process manager that's perfect for managing queue workers. It monitors your workers, restarts them if they crash, and provides easy log management.

First, install supervisord on your server:

```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

Create a configuration file for your queue worker at `/etc/supervisor/conf.d/kirby-queues.conf`:

```ini
[program:kirby-queues]
process_name=%(program_name)s_%(process_num)02d
# For local installation:
command=/path/to/your/site/vendor/bin/kirby queues:work --dir /path/to/your/site
# For global installation (find path with 'which kirby'):
# command=/usr/local/bin/kirby queues:work --dir /path/to/your/site
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/kirby-queues.log
stdout_logfile_maxbytes=0  ; Disable log rotation (optional)
stopwaitsecs=3600
```

This configuration:
- Runs the worker as the `www-data` user (adjust to match your web server user)
- Automatically starts the worker on boot
- Restarts the worker if it crashes
- Logs output to `/var/log/kirby-queues.log` (can be disabled since Queues stores logs internally)
- Waits up to 1 hour for jobs to finish when stopping

After creating the configuration, reload supervisord:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start kirby-queues:*
```

## Systemd Setup

Systemd is the default init system on most modern Linux distributions. It's built-in and provides excellent integration with system logs.

Create a service file at `/etc/systemd/system/kirby-queues.service`:

```ini
[Unit]
Description=Kirby Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
StandardOutput=append:/var/log/kirby-queues.log
StandardError=append:/var/log/kirby-queues-error.log
# For local installation:
ExecStart=/path/to/your/site/vendor/bin/kirby queues:work --dir /path/to/your/site
# For global installation (find path with 'which kirby'):
# ExecStart=/usr/local/bin/kirby queues:work --dir /path/to/your/site

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable kirby-queues
sudo systemctl start kirby-queues
```

Check the status:

```bash
sudo systemctl status kirby-queues
```

View logs:

```bash
sudo journalctl -u kirby-queues -f
```

## Monitoring

### Queue Status

Check the current queue status using the built-in status command:

```bash
kirby queues:status
```

This shows you:
- Number of jobs in each status (pending, processing, completed, failed)
- Jobs per queue
- Recent job activity


## Best Practices

### Deployment

When deploying new code:

1. Deploy your code changes
2. Restart workers to pick up the new code:
   ```bash
   sudo supervisorctl restart kirby-queues:*
   # or
   sudo systemctl restart kirby-queues
   ```

### Queue Priorities

Run separate workers for different queue priorities:

```bash
# High priority worker with more resources
kirby queues:work --queue=high --memory=512

# Default worker
kirby queues:work --queue=default

# Low priority worker with longer sleep
kirby queues:work --queue=low --sleep=10
```

### Graceful Shutdown

Workers listen for termination signals and finish processing the current job before shutting down. This prevents data corruption and ensures jobs complete successfully.
