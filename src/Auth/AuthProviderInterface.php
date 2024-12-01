<?php

namespace CassandraNative\Auth;

interface AuthProviderInterface
{
    /**
     * Returns the fully qualified name of the Java class this authentication provider answers for. The class name
     * Cassandra is configured for can be found in the cassandra.yaml file under the authenticator.class_name directive
     *
     * @return string
     */
    public function mechanism(): string;

    /**
     * The response to an authentication response from a Cassandra node
     *
     * @return string
     */
    public function response(): string;
}
