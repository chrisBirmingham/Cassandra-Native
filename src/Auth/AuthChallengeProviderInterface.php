<?php

namespace CassandraNative\Auth;

interface AuthChallengeProviderInterface
{
    /**
     * Method called when cassandra issues an authentication challenge after an initial authentication response. Some
     * authentication providers, such as plaintext auth providers, don't require an auth challenge
     *
     * @param mixed $challenge  A bytes token describing the challenge issued by the cassandra node
     * @return string           A string response to the auth challenge
     */
    public function challengeResponse(mixed $challenge): string;
}
