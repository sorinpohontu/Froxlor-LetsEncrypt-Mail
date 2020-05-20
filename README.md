# LetsEncrypt-SAN-Postfix-Dovecot
LetsEncrypt SAN for Postfix Dovecot

printf 'quit\n' | openssl s_client -connect mail.domain.tld:25 -starttls smtp | openssl x509 -noout -enddate
printf 'quit\n' | openssl s_client -connect mail.domain.tld:25 -starttls smtp | openssl x509 -noout -text | grep DNS:
