# certs

Certificates the application reads. `docker-compose.yml` mounts this folder read only into the container at
`/var/www/html/certs`.

Put the root CA of the domain controller here as `ca.pem` (PEM, "Base-64 encoded X.509") when `LDAP_TLS_VERIFY=true`,
with `LDAP_CA_CERT=/var/www/html/certs/ca.pem` in `.env`. Nothing in this folder but this file is committed.
