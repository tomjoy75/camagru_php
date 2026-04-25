#!/bin/sh
# When MAILPIT_HOST is set, relay PHP mail() through msmtp to Mailpit (SMTP 1025).
# Otherwise keep the default dev server; mail() will depend on the host (often no MTA in-container).
set -e
if [ -n "$MAILPIT_HOST" ]; then
    cat > /etc/msmtprc <<EOF
defaults
logfile -

account default
host ${MAILPIT_HOST}
port 1025
from ${APP_MAIL_FROM:-camagru@example.com}
tls off
EOF
    chmod 600 /etc/msmtprc
    exec php -d "sendmail_path=/usr/bin/msmtp -t" -S 0.0.0.0:8080 -t public public/index.php
else
    exec php -S 0.0.0.0:8080 -t public public/index.php
fi
