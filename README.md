# LetsEncrypt-SAN-Postfix-Dovecot

* Check certificate enddate    
`printf 'quit\n' | openssl s_client -connect mail.domain.tld:25 -starttls smtp 2>/dev/null | openssl x509 -noout -enddate`


* [Check certificate SANs](https://stackoverflow.com/a/57990008)    
`printf 'quit\n' | openssl s_client -connect mail.domain.tld:25 -starttls smtp 2>/dev/null | openssl x509 -noout -text | perl -l -0777 -ne '@names=/\bDNS:([^\s,]+)/g; print join("\n", sort @names);'`
