# PHP CQL

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Native [Apache Cassandra](https://cassandra.apache.org) and
[ScyllaDB](https://www.scylladb.com) connector for PHP applications
using the CQL binary protocol (v4), without the need for an external
extension.

Requires [PHP](https://www.php.net/) version >=8, Cassandra >1.2,
and any ScyllaDB version.

Much of the API is built to emulate
the [Datastax PHP Driver](https://docs.datastax.com/en/developer/php-driver/1.3/index.html).
Original work by Uri Hartmann

## Installation

```bash
$ composer require intermaterium/cassandra-native
```

## Features

* Simple and Prepared Statements
* SSL Encryption
* Persistent Connections
* Compression via LZ4 and Snappy.
* Authentication

## Missing Features

* Batch Statements
* Async queries
* Result Paging
* Tuples and User Defined Types

## Usage

### Cluster

A Cassandra cluster can be built via the `ClusterBuilder` class.
By default, the Cluster will try to connect to localhost.

```php
$clusterBuilder = new \CassandraNative\Cluster\ClusterBuilder();
$cassandra = $clusterBuilder->build();
```

You can specify a set of IP/hostnames to connect to using the
`withContactPoints` method.

The client will attempt to connect to one of the contact points 
at random. If the connection fails it will try another host until 
all contact points have been attempted or max connection attempts,
configured with the `withMaxConnectionAttempts` method,
has been reached, default is 3 attempts. If the client cannot connect to 
any of the provided hosts an `NoHostsAvailableException` is thrown.

```php
$clusterBuilder = new \CassandraNative\Cluster\ClusterBuilder();
$clusterBuilder->withContactPoints(['1.0.0.0', '2.0.0.0']);
$cassandra = $clusterBuilder->build();
```

When connecting, the created Cassandra instance doesn't connect to
a specific keyspace. Calling `connect` on the created Cassandra
instance is the same as performing a `USE $keyspace` query against 
the connection.

```php
$cassandra->connect('system');
```

### SSL

You can turn on SSL Encryption via the `SSLBuilder` class and
pass the result of a call to the `build` method to the `withSSL`
method of a cluster builder instance.

```php
$sslBuilder = new \CassandraNative\SSL\SSLBuilder();
$sslBuilder
    ->withClientCert(__DIR__ . '/certs/localhost.cer')
    ->withPrivateKey(__DIR__ . '/certs/localhost.key.pem');
    ->withTrustedCerts(__DIR__ . '/certs/localhost.cer.pem');

$clusterBuilder->withSSL($sslBuilder->build());
```

### Compression

Compression can be enabled by calling the `withCompression` method
on the cluster builder.

```php
$clusterBuilder->withCompression(true);
```

The client checks to see if the LZ4 and Snappy extensions are loaded by
PHP and then query the Cassandra node to see which compression algorithms
are supported. The client will then choose the compression algorithm that
both the client and server support. If both algorithms are supported, the 
client will choose LZ4 over Snappy.

If compression is requested and the two extensions aren't loaded or 
the Cassandra node doesn't support compression, an exception is 
thrown. An exception is also thrown if the client and Cassandra node
support different compression algorithms.

### Authentication

Authentication can be enabled by providing an Authentication Provider 
to the cluster build via the `withCredentials` method. With this library
is the `PasswordAuthenticator` provider which accepts a plaintext username 
and password.

```php
$authProvider = new \CassandraNative\Auth\PasswordAuthenticator('cassandra', 'cassandra');
$clusterBuilder->withCredentials($authProvider);
```

For other SASL based authentication methods you'll need to provide/use your 
own implementation. This can be done by creating a class which implements the 
`AuthProviderInterface`.

```php
<?php 

class KeberosProvider implements \CassandraNative\Auth\AuthProviderInterface
{
    public function mechanism(): string
    {
        return 'java class name';
    }    

    public function response(): string
    {
        return 'i am an initial response';
    }
}
```

The `mechanism` method returns the fully qualified name of the java class 
cassandra is configured to use. This name can be found in the `authenticator.class_name` 
directive of the cassandra.yaml config.

The `response` method is called when the first auth challenge is issued. Some 
auth providers will only require sending this response.

For auth providers that require responding to subsequent authentication challenges 
the `AuthChallengeProvderInterface` is provided. This interface provides the `challengeResponse` 
method. This method accepts an `token` parameter which contains the binary representation 
of a token sent back from the Cassandra node describing how to respond to the auth
challenge. 

If a provider does not implement the `AuthChallengeProviderInterface` and an auth
challenge is issued after the first response, an `AuthenticationException` is thrown.

### Statements

The client currently only supports two types of statements, `Simple`
and `Prepared`. Both types of statement are executed via the `execute` method
on the `Cassandra` instance. The execute method accepts the statement, an optional
array of values to bind to parameters and an optional consistency level which
overrides the default consistency.

The `execute` method returns a `Rows` class which implements the `ArrayAccess` and
`Iterator` interfaces.

#### Simple Statements

Simple statements use the `SimpleStatement` class.

```php
$stmt = new \CassandraNative\Statement\SimpleStatement('DESCRIBE TABLES');
$rows = $cassandra->execute($stmt);
```

Simple statements support parameterised values.

```php
$stmt = new \CassandraNative\Statement\SimpleStatement('SELECT col1, col2, col3 FROM my_table WHERE id=?')
$rows = $cassandra->execute(
    $stmt,
    [
        [1001, Cassandra::COLUMNTYPE_BIGINT]
    ]
);

// Or

$stmt = new \CassandraNative\Statement\SimpleStatement('SELECT col1, col2, col3 FROM my_table WHERE id=:id')
$rows = $cassandra->execute(
    $stmt,
    [
        'id' => [1001, Cassandra::COLUMNTYPE_BIGINT]
    ]
);
```

You must specify the bound parameters type when using a simple statement

#### Prepared Statements

Prepared Statements are created via the `prepare` method on the `Cassandra` instance.

```php
$stmt = $cassandra->prepare('UPDATE my_table SET col2=?,col3=? WHERE col1=?');
$values = ['col2' => 5, 'col3' => '0x55', 'col1' => 'five'];
$rows = $cassandra->execute($stmt, $values);
```

Unlike Simple Statements, you don't need to specify the bound values type.

## External links

1. Datastax's blog introducing the binary protocol:
   http://www.datastax.com/dev/blog/binary-protocol

2. CQL definitions
   https://cassandra.apache.org/_/native_protocol.html

## License

    The MIT License (MIT)

    Copyright (c) 2023 Uri Hartmann
    Copyright (c) 2024 Christopher Birmingham

    Permission is hereby granted, free of charge, to any person obtaining a
    copy of this software and associated documentation files (the "Software"),
    to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense,
    and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in
    all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
    FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
    DEALINGS IN THE SOFTWARE.
