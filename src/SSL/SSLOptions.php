<?php

namespace CassandraNative\SSL;

class SSLOptions
{
    public function __construct(
        protected ?string $trustedCerts,
        protected bool $verify,
        protected ?string $clientCert,
        protected ?string $privateKey,
        protected ?string $passphrase,
    ) {}

    /**
     * Returns the SSL options in the format supported by stream_context_create
     *
     * @return array
     */
    public function get(): array
    {
        $options = [
            'verify_peer' => $this->verify,
            'verify_peer_name' => $this->verify,
        ];

        if ($this->trustedCerts) {
            $options['cafile'] = $this->trustedCerts;
        }

        if ($this->clientCert) {
            $options['local_cert'] = $this->clientCert;
        }

        if ($this->privateKey) {
            $options['local_pk'] = $this->privateKey;

            if ($this->passphrase) {
                $options['passphrase'] = $this->passphrase;
            }
        }

        return $options;
    }
}
