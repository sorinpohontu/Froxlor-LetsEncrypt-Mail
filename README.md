# Let's Encrypt multi-domain name certificates for Postfix and Dovecot on Froxlor Control Panel

Purpose of this project is to manage [Let's Encrypt](https://letsencrypt.org/) [multi-domain name (SAN)](https://www.digicert.com/subject-alternative-name.htm) certificates for [Postfix](http://www.postfix.org/) and [Dovecot](https://www.dovecot.org/) running on a [Froxlor Server Management Panel](https://froxlor.org/), with [getssl](https://github.com/srvrco/getssl).

This setup is recommended if you're using custom domain name for email domains hosted on server and you want to avoid certificate mismatch errors from your email client (Thunderbird, Outlook, Apple Mailetc.)

Each email domain hosted on the server must have a `A` or `CNAME` record pointing to the same IP as the `MX`.

This record will be used to validate each additional domain (SAN) included the SSL certificate using [Let’s Encrypt HTTP ACME Challenge](https://letsencrypt.org/docs/challenge-types/).

Here is an example as BIND zone for `example.com`:
```
...
        IN    MX    10    mail.example.com.
www     IN    A     192.168.0.2  ; Froxlor domain IP
mail    IN    A     192.168.0.2  ; Froxlor domain IP
```

You can configure `MAIL_HOST` in `config.php` if you use another custom email host for your hosted domains.

## Installation
* Download latest release from this repository
* Extract the content of it in a folder of you choice (make sure you preserve the folder structure)
* Run `php mail-san.php`

On first run, the installer will:
* Download and install `getSSL`
* Update Postfix `/etc/postfix/main.cf` TLS configuration (`smtpd_tls_cert_file`, `smtpd_tls_key_file`, `smtpd_tls_CAfile`)
* Update Dovecot `/etc/dovecot/conf.d/10-ssl.conf` SSL configuration (`ssl_cert`, `ssl_key`, `ssl_ca`)
* Add a `cron.daily` job to update the certificates

Note: after running the jobs, if there are changes in local certificates services will be restarted (config: `RELOAD_MAIL_CMD`, `RELOAD_WWW_CMD`).

## Checking the certificates

* Certificate enddate    
`printf 'quit\n' | openssl s_client -connect mail.example.com:25 -starttls smtp 2>/dev/null | openssl x509 -noout -enddate`

* [Certificate SANs](https://stackoverflow.com/a/57990008)    
`printf 'quit\n' | openssl s_client -connect mail.example.com:25 -starttls smtp 2>/dev/null | openssl x509 -noout -text | perl -l -0777 -ne '@names=/\bDNS:([^\s,]+)/g; print join("\n", sort @names);'`
