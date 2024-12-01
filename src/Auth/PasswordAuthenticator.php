<?php

namespace CassandraNative\Auth;

class PasswordAuthenticator implements AuthProviderInterface
{
    protected string $username;

    protected string $password;

    /**
     * @param string $username
     * @param string $password
     */
    public function __construct(
        string $username,
        #[\SensitiveParameter] string $password
    ) {
        $this->username = $username;
        $this->password = $password;
    }

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
