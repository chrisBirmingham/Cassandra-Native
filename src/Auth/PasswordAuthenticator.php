<?php

namespace CassandraNative\Auth;

class PasswordAuthenticator implements AuthProviderInterface
{
    public function __construct(
        protected string $username,
        #[\SensitiveParameter] protected string $password,
    ) {}

    /**
     * @inheritDoc
     */
    public function mechanism(): string
    {
        return 'org.apache.cassandra.auth.PasswordAuthenticator';
    }

    /**
     * @inheritDoc
     */
    public function response(): string
    {
        return "\x00$this->username\x00$this->password";
    }
}
