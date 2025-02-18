<?php

namespace CassandraNative;

use CassandraNative\Auth\AuthChallengeProviderInterface;
use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Cluster\ClusterOptions;
use CassandraNative\Compression\CompressorInterface;
use CassandraNative\Connection\Socket;
use CassandraNative\Exception\AuthenticationException;
use CassandraNative\Exception\CassandraException;
use CassandraNative\Exception\CompressionException;
use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\NoHostsAvailableException;
use CassandraNative\Exception\ProtocolException;
use CassandraNative\Exception\QueryException;
use CassandraNative\Exception\ServerException;
use CassandraNative\Exception\TimeoutException;
use CassandraNative\Exception\UnauthorizedException;
use CassandraNative\Result\Rows;
use CassandraNative\SSL\SSLOptions;
use CassandraNative\Statement\PreparedStatement;
use CassandraNative\Statement\SimpleStatement;
use CassandraNative\Statement\StatementInterface;
use CassandraNative\Type\ListFactory;
use CassandraNative\Type\ListType;
use CassandraNative\Type\MapType;
use CassandraNative\Type\MapFactory;
use CassandraNative\Type\TupleType;
use CassandraNative\Type\TupleFactory;
use CassandraNative\Type\UDTFactory;
use CassandraNative\Type\UDTType;

/**
 * Cassanda Connector
 *
 * A native Cassandra connector for PHP based on the CQL binary protocol v4,
 * without the need for any external extensions.
 *
 * Requires PHP version >8, and Cassandra >1.2.
 *
 * Usage and more information is found on README.md
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2023 Uri Hartmann
 * Copyright (c) 2024 Christopher Birmingham
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @category  Database
 * @package   Cassandra
 * @author    Uri Hartmann
 * @copyright 2023 Uri Hartmann
 * @license   http://opensource.org/licenses/MIT The MIT License (MIT)
 * @version   2023.07.08
 * @link      https://www.humancodes.org/projects/php-cql
 */

class Cassandra
{
    public const CONSISTENCY_ANY          = 0x0000;
    public const CONSISTENCY_ONE          = 0x0001;
    public const CONSISTENCY_TWO          = 0x0002;
    public const CONSISTENCY_THREE        = 0x0003;
    public const CONSISTENCY_QUORUM       = 0x0004;
    public const CONSISTENCY_ALL          = 0x0005;
    public const CONSISTENCY_LOCAL_QUORUM = 0x0006;
    public const CONSISTENCY_EACH_QUORUM  = 0x0007;
    public const CONSISTENCY_LOCAL_ONE    = 0x000A;

    public const COLUMNTYPE_CUSTOM    = 0x0000;
    public const COLUMNTYPE_ASCII     = 0x0001;
    public const COLUMNTYPE_BIGINT    = 0x0002;
    public const COLUMNTYPE_BLOB      = 0x0003;
    public const COLUMNTYPE_BOOLEAN   = 0x0004;
    public const COLUMNTYPE_COUNTER   = 0x0005;
    public const COLUMNTYPE_DECIMAL   = 0x0006;
    public const COLUMNTYPE_DOUBLE    = 0x0007;
    public const COLUMNTYPE_FLOAT     = 0x0008;
    public const COLUMNTYPE_INT       = 0x0009;
    public const COLUMNTYPE_TEXT      = 0x000A;
    public const COLUMNTYPE_TIMESTAMP = 0x000B;
    public const COLUMNTYPE_UUID      = 0x000C;
    public const COLUMNTYPE_VARCHAR   = 0x000D;
    public const COLUMNTYPE_VARINT    = 0x000E;
    public const COLUMNTYPE_TIMEUUID  = 0x000F;
    public const COLUMNTYPE_INET      = 0x0010;
    public const COLUMNTYPE_LIST      = 0x0020;
    public const COLUMNTYPE_MAP       = 0x0021;
    public const COLUMNTYPE_SET       = 0x0022;
    public const COLUMNTYPE_UDT       = 0x0030;
    public const COLUMNTYPE_TUPLE     = 0x0031;

    protected const OPCODE_ERROR          = 0x00;
    protected const OPCODE_STARTUP        = 0x01;
    protected const OPCODE_READY          = 0x02;
    protected const OPCODE_AUTHENTICATE   = 0x03;
    protected const OPCODE_OPTIONS        = 0x05;
    protected const OPCODE_SUPPORTED      = 0x06;
    protected const OPCODE_QUERY          = 0x07;
    protected const OPCODE_RESULT         = 0x08;
    protected const OPCODE_PREPARE        = 0x09;
    protected const OPCODE_EXECUTE        = 0x0A;
    protected const OPCODE_REGISTER       = 0x0B;
    protected const OPCODE_EVENT          = 0x0C;
    protected const OPCODE_BATCH          = 0x0D;
    protected const OPCODE_AUTH_CHALLENGE = 0x0E;
    protected const OPCODE_AUTH_RESPONSE  = 0x0F;
    protected const OPCODE_AUTH_SUCCESS   = 0x10;

    public const BATCH_LOGGED   = 0x00;
    public const BATCH_UNLOGGED = 0x01;
    public const BATCH_COUNTER  = 0x02;

    protected const RESULT_KIND_VOID          = 0x001;
    protected const RESULT_KIND_ROWS          = 0x002;
    protected const RESULT_KIND_SET_KEYSPACE  = 0x003;
    protected const RESULT_KIND_PREPARED      = 0x004;
    protected const RESULT_KIND_SCHEMA_CHANGE = 0x005;

    protected const FLAG_COMPRESSION    = 0x01;
    protected const FLAG_TRACING        = 0x02;
    protected const FLAG_CUSTOM_PAYLOAD = 0x04;
    protected const FLAG_WARNING        = 0x08;

    protected const SERVER_ERROR           = 0x0000;
    protected const PROTOCOL_ERROR         = 0x000A;
    protected const AUTHENTICATION_ERROR   = 0x0100;
    protected const UNAVAILABLE_ERROR      = 0x1000;
    protected const OVERLOADED_ERROR       = 0x1001;
    protected const IS_BOOTSTRAPPING_ERROR = 0x1002;
    protected const TRUNCATE_ERROR         = 0x1003;
    protected const WRITE_TIMEOUT_ERROR    = 0x1100;
    protected const READ_TIMEOUT_ERROR     = 0x1200;
    protected const READ_FAILURE_ERROR     = 0x1300;
    protected const FUNCTION_FAILURE_ERROR = 0x1400;
    protected const WRITE_FAILURE_ERROR    = 0x1500;
    protected const SYNTAX_ERROR           = 0x2000;
    protected const UNAUTHORIZED_ERROR     = 0x2100;
    protected const INVALID_ERROR          = 0x2200;
    protected const CONFIG_ERROR           = 0x2300;
    protected const ALREADY_EXISTS_ERROR   = 0x2400;
    protected const UNPREPARED_ERROR       = 0x2500;

    protected const PROTOCOL_VERSION = 4;

    protected Socket $socket;

    protected string $fullFrame = '';

    protected int $defaultConsistency;

    protected ?CompressorInterface $compressor = null;

    /**
     * @param ClusterOptions $options
     * @throws CassandraException
     */
    public function __construct(ClusterOptions $options)
    {
        $this->socket = new Socket();
        $this->defaultConsistency = $options->getDefaultConsistency();
        $this->establishConnection($options);
    }

    /**
     * Establishes a connection with a cassandra host based on options provided by the ClusterBuilder
     * 
     * @param ClusterOptions $clusterOptions
     * 
     * @throws CassandraException
     */
    protected function establishConnection(ClusterOptions $clusterOptions): void
    {
        $connectionErrors = [];
        $hosts = $clusterOptions->getHosts();
        $maxAttempts = min(count($hosts), $clusterOptions->getMaxConnectionAttempts());
        $attempt = 1;

        do {
            // Choose a random contact host to connect too. If it fails try another one until we either connect to a
            // host or hit max connection attempts
            $index = array_rand($hosts);
            $host = $hosts[$index];
            array_splice($hosts, $index, 1);

            try {
                $this->socket->connect(
                    $host,
                    $clusterOptions->getPort(),
                    $clusterOptions->getPersistentSessions(),
                    $clusterOptions->getConnectTimeout()
                );

                break;
            } catch (ConnectionException $e) {
                $connectionErrors[$host] = $e->getMessage();

                if ($attempt === $maxAttempts) {
                    throw new NoHostsAvailableException("Failed to connect to a Cassandra Host after $maxAttempts attempt(s)", $connectionErrors);
                }

                $attempt++;
            }
        } while (true);

        $sslOptions = $clusterOptions->getSSL();
        if ($sslOptions instanceof SSLOptions) {
            $this->socket->enableSSL($sslOptions->get());
        }

        $this->socket->setTimeout($clusterOptions->getRequestTimeout());

        // Get whether we have a persistent connection before sending options request as that
        // updates the seek position from ftell
        $persistent = $this->socket->isPersistent();

        // Send an OPTIONS request and check our clients compatibility
        // Have to send on every new connection as we don't know whether to set compression
        // until we have this response. Might be a good idea to add caching in the future
        $optionsMap = $this->sendOptionsFrame();
        $this->checkCompatibility($clusterOptions, $optionsMap);

        // Don't send startup & authentication if we're using a persistent connection
        if ($persistent) {
            return;
        }

        $this->sendStartupFrame($clusterOptions);
    }

    /**
     * Sends an OPTIONS frame to the connected cassandra node and validates the response
     *
     * @return array The returned options map
     *
     * @throws CassandraException
     */
    protected function sendOptionsFrame(): array
    {
        $this->writeFrame(self::OPCODE_OPTIONS);
        return $this->supportedResult();
    }

    /**
     * Retrieves a SUPPORTED frame from the cassandra node and returns Cassandra options as a map
     *
     * @return array The returned options map
     *
     * @throws CassandraException
     */
    protected function supportedResult(): array
    {
        $frame = $this->readFrame();
        $opcode = $frame['opcode'];

        if ($opcode != self::OPCODE_SUPPORTED) {
            throw new ProtocolException('Missing SUPPORTED packet. Got ' . $opcode . ' instead', $opcode);
        }

        $body = $frame['body'];
        return $this->unpackMultimap($body);
    }

    /**
     * Checks if the client and the connected cassandra node support the same options
     *
     * @param ClusterOptions $clusterOptions Client configuration
     * @param array $optionsMap              The options map from the cluster to check against
     *
     * @throws CassandraException
     */
    protected function checkCompatibility(ClusterOptions $clusterOptions, array $optionsMap): void
    {
        $configuredCompressors = $clusterOptions->getCompressors();
        
        // We only support checking compression at the moment. Early return if we're not set to use it
        if (empty($configuredCompressors)) {
            return;
        }

        $supportedCompressors = $optionsMap['COMPRESSION'] ?? [];

        if (empty($supportedCompressors)) {
            throw new ProtocolException('Client configured to use compression but connected Cassandra Cluster only supports uncompressed communication');
        }

        // Loop through supported compressors and use first one we match on
        foreach ($configuredCompressors as $compressor) {
            if (in_array($compressor->getName(), $supportedCompressors)) {
                $this->compressor = $compressor;
                return;
            }
        }

        throw new ProtocolException(
            sprintf(
                'Client configured to use %s compression but Cassandra Cluster supports %s compression',
                implode(', ', $configuredCompressors),
                implode(', ', $supportedCompressors)
            )
        );
    }

    /**
     * Sends a STARTUP frame to the cassandra node.
     *
     * @throws CassandraException
     */
    protected function sendStartupFrame(ClusterOptions $clusterOptions): void
    {
        $startBody = [
            'CQL_VERSION' => '3.0.0',
            'DRIVER_NAME' => 'PHP Cassandra Native Driver',
            'DRIVER_VERSION' => '3.0.2'
        ];

        if ($this->compressor instanceof CompressorInterface) {
            $startBody['COMPRESSION'] = $this->compressor->getName();
        }

        // Writes a STARTUP frame
        $frameBody = $this->packStringMap($startBody);
        $this->writeFrame(self::OPCODE_STARTUP, $frameBody);

        $this->startupResult($clusterOptions);
    }

    /**
     * Retrieves the result of a STARTUP request
     *
     * @param ClusterOptions $clusterOptions
     *
     * @throws CassandraException
     */
    protected function startupResult(ClusterOptions $clusterOptions): void
    {
        $authProvider = $clusterOptions->getAuthProvider();
        $frame = $this->readFrame();
        $opcode = $frame['opcode'];
        $body = $frame['body'];

        switch ($opcode) {
            case self::OPCODE_READY:
                if ($authProvider instanceof AuthProviderInterface) {
                    throw new ConnectionException("Client is configured with an auth provider but Cassandra didn't issue an auth challenge");
                }
                break;
            case self::OPCODE_AUTHENTICATE:
                $offset = 0;
                $this->handleAuth($this->popString($body, $offset), $authProvider);
                break;
            default:
                throw new ProtocolException('Missing READY or AUTHENTICATE packet. Got ' . $opcode . ' instead', $opcode);
        }
    }

    /**
     * Respond to an authentication challenge issued by the cassandra node
     *
     * @param string $authMechanism
     * @param ?AuthProviderInterface $authProvider
     *
     * @throws CassandraException
     */
    protected function handleAuth(string $authMechanism, ?AuthProviderInterface $authProvider): void
    {
        if (!($authProvider instanceof AuthProviderInterface)) {
            throw new AuthenticationException('Cassandra sent an auth challenge but an Authentication provider was not provided');
        }

        if ($authProvider->mechanism() !== $authMechanism) {
            throw new AuthenticationException("Cassandra sent back an auth challenge for $authMechanism which doesn't match the one the client is configured for");
        }

        $authResponseBody = $authProvider->response();
        $authResponseBody = $this->packLongString($authResponseBody);
        $this->writeFrame(self::OPCODE_AUTH_RESPONSE, $authResponseBody);

        $frame = $this->readFrame();
        $opcode = $frame['opcode'];
        $body = $frame['body'];

        switch ($opcode) {
            case self::OPCODE_AUTH_SUCCESS:
                // The initial auth response can send back a success without a challenge
                return;
            case self::OPCODE_AUTH_CHALLENGE:
                if (!($authProvider instanceof AuthChallengeProviderInterface)) {
                    throw new AuthenticationException("Cassandra issued a challenge response but provider doesn't support challenges");
                }

                // @todo This code could infinite loop. Possibly add a challenge limit
                do {
                    $authChallengeResponseBody = $authProvider->challengeResponse($body);
                    $authChallengeResponseBody = $this->packLongString($authChallengeResponseBody);
                    $this->writeFrame(self::OPCODE_AUTH_RESPONSE, $authChallengeResponseBody);

                    $frame = $this->readFrame();
                    $opcode = $frame['opcode'];
                    $body = $frame['body'];
                } while ($opcode == self::OPCODE_AUTH_CHALLENGE);
                break;
            default:
                throw new ProtocolException('Missing AUTH_SUCCESS or AUTH_CHALLENGE packet. Got ' . $opcode . ' instead', $opcode);
        }
    }

    /**
     * Connects the client to the specified keyspace. Same as using a USE $keyspace query
     * 
     * @param string $keyspace
     * 
     * @throws CassandraException
     */
    public function connect(string $keyspace): void
    {
        $stmt = new SimpleStatement('USE ' . $keyspace);
        $this->execute($stmt);
    }

    /**
     * Closes an open client connection.
     */
    public function close(): void
    {
        $this->socket->close();
    }

    /**
     * Queries the database using the given CQL.
     *
     * @param StatementInterface $stmt The query to run.
     * @param array $values            Values to bind in a sequential or key=>value format, where key is the column's name.
     * @param ?int $consistency        Consistency level for the operation.
     * 
     * @return Rows Result of the query. Might be an array of rows (for SELECT), or the operation's result
     *              (for USE, CREATE, ALTER, UPDATE).
     * 
     * @throws CassandraException
     */
    public function execute(
        StatementInterface $stmt,
        array $values = [],
        ?int $consistency = null
    ): Rows {
        $consistency ??= $this->defaultConsistency;

        $rows = match (true) {
            $stmt instanceof PreparedStatement => $this->executePreparedStatement($stmt, $values, $consistency),
            $stmt instanceof SimpleStatement => $this->executeSimpleStatement($stmt, $values, $consistency)
        };

        return new Rows($rows);
    }

    /**
     * Prepares a query statement.
     *
     * @param string $cql The query to prepare.
     *
     * @return PreparedStatement The statement's information to be used with the execute method.
     *
     * @throws CassandraException
     */
    public function prepare(string $cql): PreparedStatement
    {
        // Prepares the frame's body
        $frame = $this->packLongString($cql);

        // Writes a PREPARE frame and return the result
        $retval = $this->requestResult(self::OPCODE_PREPARE, $frame);

        return new PreparedStatement($retval['id'], $retval['columns']);
    }

    /**
     * Executes a prepared statement.
     *
     * @param PreparedStatement $stmt The prepared statement as returned from the prepare method.
     * @param array $values           Bind values for the prepared statement 
     * @param int $consistency        Consistency level for the operation.
     *
     * @return array Result of the execution. Might be an array of rows (for SELECT), or the operation's result
     *              (for USE, CREATE, ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    protected function executePreparedStatement(
        PreparedStatement $stmt,
        array $values,
        int $consistency
    ): array {
        // Prepares the frame's body - <id><count><values map>
        $frame = base64_decode($stmt->getId());
        $frame = $this->packString($frame) .
            $this->packShort($consistency) .
            $this->packByte(0x01) . // values only
            $this->packShort(count($values));

        foreach ($stmt->getColumns() as $key => $column) {
            if (!isset($values[$key])) {
                throw new QueryException('Missing value for bound parameter ' . $key);
            }

            $value = $values[$key];

            $data = $this->packValue(
                $value,
                $column['type'],
                $column['subTypes'],
                $column['options']
            );

            $frame .= $this->packLongString($data);
        }

        // Writes a EXECUTE frame and return the result
        return $this->requestResult(self::OPCODE_EXECUTE, $frame);
    }

    /**
     * Executes a simple statement
     * 
     * @param SimpleStatement $stmt The statement to be run.
     * @param array $values         Values to bind to the statement being run.
     * @param int $consistency      Consistency level for the operation.
     * 
     * @return array Result of the execution. Might be an array of rows (for SELECT), or the operation's result
     *               (for USE, CREATE, ALTER, UPDATE).
     * 
     * @throws CassandraException
     */
    protected function executeSimpleStatement(
        SimpleStatement $stmt,
        array $values,
        int $consistency
    ): array {
        // Prepares the frame's body
        // TODO: Support the new <flags> byte
        $frame = $this->packLongString($stmt->getStatement()) . $this->packShort($consistency);
        if (count($values)) {
            $valuesData = '';
            $namedParameters = false;
            foreach ($values as $key => $value) {
                $namedParameters = $namedParameters || is_string($key);

                if ($namedParameters) {
                    $valuesData .= $this->packString($key);
                }

                $data = $this->packValue($value[0], $value[1]);

                $valuesData .= $this->packLongString($data);
            }

            $frame .= $this->packByte(0x01 | ($namedParameters ? 0x40 : 0x00)) .
                $this->packShort(count($values)) .
                $valuesData;
        } else {
            $frame .= $this->packByte(0x00);
        }

        return $this->requestResult(self::OPCODE_QUERY, $frame);
    }

    /**
     * Writes a (QUERY/PREPARE/EXCUTE) frame, reads the result, and parses it.
     *
     * @param int    $opcode Frame's opcode.
     * @param string $body   Frame's body.
     *
     * @return array Result of the request. Might be an array of rows (for SELECT), or the operation's result
     *               (for USE, CREATE, ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    protected function requestResult(int $opcode, string $body): array
    {
        // Writes the frame
        $this->writeFrame($opcode, $body);

        // Reads incoming frame
        $frame = $this->readFrame();
        $opcode = $frame['opcode'];

        // Parses the incoming frame
        if ($opcode == self::OPCODE_RESULT) {
            return $this->parseResult($frame['body']);
        }

        throw new ProtocolException('Unknown opcode returned from cassandra', $opcode);
    }

    /**
     * Packs and writes a frame to the socket.
     *
     * @param int $opcode   Frame's opcode.
     * @param string $body  Frame's body.
     * @param int $response Frame's response flag.
     * @param int $stream   Frame's stream id.
     *
     * @throws CassandraException
     */
    protected function writeFrame(int $opcode, string $body = '', int $response = 0, int $stream = 0): void
    {
        // Prepares the outgoing packet
        $frame = $this->packFrame($opcode, $body, $response, $stream);

        // Writes frame to socket
        $this->socket->write($frame);
    }

    /**
     * Converts an error message returned from Cassandra into an exception
     * 
     * @param int $errorCode       The error code returned from cassandra
     * @param string $errorMessage The error message returned from cassandra
     * 
     * @throws CassandraException
     */
    protected function convertCassandraErrorToException(int $errorCode, string $errorMessage): void
    {
        $exception = match ($errorCode) {
            self::SERVER_ERROR, self::OVERLOADED_ERROR, self::UNAVAILABLE_ERROR, self::IS_BOOTSTRAPPING_ERROR, self::TRUNCATE_ERROR => ServerException::class,
            self::PROTOCOL_ERROR => ProtocolException::class,
            self::AUTHENTICATION_ERROR => AuthenticationException::class,
            self::WRITE_TIMEOUT_ERROR, self::READ_TIMEOUT_ERROR => TimeoutException::class,
            self::READ_FAILURE_ERROR, self::FUNCTION_FAILURE_ERROR, self::WRITE_FAILURE_ERROR, self::SYNTAX_ERROR, self::INVALID_ERROR, self::CONFIG_ERROR, self::ALREADY_EXISTS_ERROR, self::UNPREPARED_ERROR => QueryException::class,
            self::UNAUTHORIZED_ERROR => UnauthorizedException::class
        };

        throw new $exception($errorMessage, $errorCode);
    }

    /**
     * Parses a returned Frame send back by cassandra. Checks for any errors returned and throws an exception
     * 
     * @param string $header The returned frame header
     * @param string $body   The returned frame body
     * 
     * @return array         The parsed frame converted into an opcode and body
     * 
     * @throws CassandraException
     */
    protected function parseIncomingFrame(string $header, string $body): array
    {
        $flags = ord($header[1]);

        // Unpack the header to its contents:
        // <byte version><byte flags><uint16 stream><byte opcode><int length>
        $opcode = ord($header[4]);

        $this->fullFrame = $header . $body;

        if ($flags & self::FLAG_COMPRESSION) {
            if (($body = $this->compressor->uncompress($body)) === false) {
                throw new CompressionException('Could not uncompress response from Cassandra');
            }
        }

        if ($flags & self::FLAG_WARNING) {
            $iPos = 0;
            $warningCount = $this->popShort($body, $iPos);
            for (; $warningCount; $warningCount--) {
                $warning = $this->popString($body, $iPos);
                trigger_error('Warning returned while processing Cassandra query: ' . $warning, E_USER_WARNING);
            }

            $body = substr($body, $iPos);
        }

        // If we got an error - trigger it and return an error
        if ($opcode == self::OPCODE_ERROR) {
            // ERROR: <int code><string msg>
            $errCode = $this->intFromBin($body, 0, 4);
            $bodyOffset = 4;  // Must be passed by reference
            $errMsg = $this->popString($body, $bodyOffset);
            $this->convertCassandraErrorToException($errCode, $errMsg);
        }

        return [
            'opcode' => $opcode,
            'body' => $body
        ];
    }

    /**
     * Reads pending frame from the socket.
     *
     * @return array Incoming data.
     *
     * @throws CassandraException
     */
    protected function readFrame(): array
    {
        // Read the 9 bytes header
        $header = $this->socket->read(9);
        $length = $this->intFromBin($header, 5, 4);

        // Read frame body, if exists
        $body = '';
        
        if ($length) {
            $body = $this->socket->read($length);
        }

        return $this->parseIncomingFrame($header, $body);
    }

    /**
     * Parses a RESULT frame.
     *
     * @param string $body Frame's body
     *
     * @return array       Parsed frame. Might be an array of rows (for SELECT), or the operation's result
     *                     (for USE, CREATE, ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    protected function parseResult(string $body): array
    {
        // Parse RESULTS opcode
        $bodyOffset = 0;
        $kind = $this->popInt($body, $bodyOffset);

        switch ($kind) {
            case self::RESULT_KIND_VOID:
                return [['result' => 'success']];
            case self::RESULT_KIND_ROWS:
                return $this->parseRows($body, $bodyOffset);
            case self::RESULT_KIND_SET_KEYSPACE:
                $keyspace = $this->popString($body, $bodyOffset);
                return [['keyspace' => $keyspace]];
            case self::RESULT_KIND_PREPARED:
                // <string id><metadata>
                $id = base64_encode($this->popString($body, $bodyOffset));
                $metadata = $this->parseRowsMetadata($body, $bodyOffset, true);
                $columns = [];

                foreach ($metadata as $column) {
                    $columns[$column['name']] = [
                        'type' => $column['type'],
                        'subTypes' => $column['subTypes'],
                        'options' => $column['options']
                    ];
                }

                return [
                    'id' => $id,
                    'columns' => $columns
                ];
            case self::RESULT_KIND_SCHEMA_CHANGE:
                // <string change><string keyspace><string table>
                $change = $this->popString($body, $bodyOffset);
                $target = $this->popString($body, $bodyOffset);
                $options = $this->popString($body, $bodyOffset);
                return [[
                    'change' => $change,
                    'target' => $target,
                    'options' => $options
                ]];
        }

        throw new ProtocolException('Unknown result kind ' . $kind . ' full frame: ' . bin2hex($this->fullFrame));
    }

    /**
     * Parses a RESULT Rows metadata (also used for RESULT Prepared), starting from the offset, and advancing it in the
     * process.
     *
     * @param string $body    Metadata body.
     * @param int $bodyOffset Metadata body offset to start from.
     *
     * @return array Columns list
     */
    protected function parseRowsMetadata(string $body, int &$bodyOffset, bool $readPk = false): array
    {
        $flags = $this->popInt($body, $bodyOffset);
        $columnsCount = $this->popInt($body, $bodyOffset);

        if ($readPk) {
            $pkCount = $this->popInt($body, $bodyOffset);

            for (; $pkCount; $pkCount--) {
                $this->popShort($body, $bodyOffset);
            }
        }

        $globalTableSpec = ($flags & 0x0001);
        if ($globalTableSpec) {
            $keyspace = $this->popString($body, $bodyOffset);
            $table = $this->popString($body, $bodyOffset);
        }

        $columns = [];

        for ($i = 0; $i < $columnsCount; $i++) {
            if (!$globalTableSpec) {
                $keyspace = $this->popString($body, $bodyOffset);
                $table = $this->popString($body, $bodyOffset);
            }

            $columnName = $this->popString($body, $bodyOffset);
            $columnType = $this->popShort($body, $bodyOffset);
            $subTypes = [];
            $options = [];

            switch ($columnType) {
                case self::COLUMNTYPE_CUSTOM:
                    $columnType = $this->popString($body, $bodyOffset);
                    break;
                case self::COLUMNTYPE_LIST:
                case self::COLUMNTYPE_SET:
                    $subType = $this->popShort($body, $bodyOffset);
                    if ($subType == self::COLUMNTYPE_CUSTOM) {
                        $subType = $this->popString($body, $bodyOffset);
                    }

                    $subTypes[] = $subType;
                    break;
                case self::COLUMNTYPE_MAP:
                    $subType1 = $this->popShort($body, $bodyOffset);
                    if ($subType1 == self::COLUMNTYPE_CUSTOM) {
                        $subType1 = $this->popString($body, $bodyOffset);
                    }

                    $subType2 = $this->popShort($body, $bodyOffset);
                    if ($subType2 == self::COLUMNTYPE_CUSTOM) {
                        $subType2 = $this->popString($body, $bodyOffset);
                    }

                    $subTypes[] = $subType1;
                    $subTypes[] = $subType2;
                    break;
                case self::COLUMNTYPE_UDT:
                    $udtKeyspace = $this->popString($body, $bodyOffset);
                    $udtName = $this->popString($body, $bodyOffset);
                    $itemCount = $this->popShort($body, $bodyOffset);

                    for (; $itemCount; $itemCount--) {
                        $subName = $this->popString($body, $bodyOffset);
                        $value = $this->popShort($body, $bodyOffset);
                        $subTypes[$subName] = $value;
                    }

                    $options['name'] = $udtName;
                    $options['keyspace'] = $udtKeyspace;
                    break;
                case self::COLUMNTYPE_TUPLE:
                    $itemCount = $this->popShort($body, $bodyOffset);
                    for (; $itemCount; $itemCount--) {
                        $subTypes[] = $this->popShort($body, $bodyOffset);
                    }
            }

            $columns[] = [
                'keyspace' => $keyspace,
                'table' => $table,
                'name' => $columnName,
                'type' => $columnType,
                'subTypes' => $subTypes,
                'options' => $options
            ];
        }
        return $columns;
    }

    /**
     * Parses a RESULT Rows kind.
     *
     * @param string $body    Frame body to parse.
     * @param int $bodyOffset Offset to start from.
     *
     * @return array Rows with associative array of the records.
     *
     * @throws CassandraException
     */
    protected function parseRows(string $body, int $bodyOffset): array
    {
        // <metadata><int count><rows_content>
        $columns = $this->parseRowsMetadata($body, $bodyOffset);
        $rowsCount = $this->popInt($body, $bodyOffset);

        $retval = [];
        for (; $rowsCount; $rowsCount--) {
            $row = [];
            foreach ($columns as $col) {
                $content = $this->popBytes($body, $bodyOffset);
                $value = $this->unpackValue(
                    $content,
                    $col['type'],
                    $col['subTypes']
                );

                $row[$col['name']] = $value;
            }
            $retval[] = $row;
        }

        return $retval;
    }

    /**
     * Packs a value to its binary form based on a column type
     *
     * @param mixed $value    Value to pack.
     * @param int $type       Column type.
     * @param array $subTypes The subtype for values that contain other values. This includes lists, maps and tuples
     *
     * @return string Binary form of the value.
     *
     * @throws \InvalidArgumentException
     * @throws CassandraException
     */
    protected function packValue(mixed $value, int $type, array $subTypes = [], array $options = []): string
    {
        return match ($type) {
            self::COLUMNTYPE_CUSTOM, self::COLUMNTYPE_BLOB => $this->packBlob($value),
            self::COLUMNTYPE_ASCII, self::COLUMNTYPE_TEXT, self::COLUMNTYPE_VARCHAR => $value,
            self::COLUMNTYPE_BIGINT, self::COLUMNTYPE_COUNTER, self::COLUMNTYPE_TIMESTAMP => $this->packBigint($value),
            self::COLUMNTYPE_BOOLEAN => $this->packBoolean($value),
            self::COLUMNTYPE_DECIMAL => $this->packDecimal($value),
            self::COLUMNTYPE_DOUBLE => $this->packDouble($value),
            self::COLUMNTYPE_FLOAT => $this->packFloat($value),
            self::COLUMNTYPE_INT => $this->packInt($value),
            self::COLUMNTYPE_UUID, self::COLUMNTYPE_TIMEUUID => $this->packUuid($value),
            self::COLUMNTYPE_VARINT => $this->packVarInt($value),
            self::COLUMNTYPE_INET => $this->packInet($value),
            self::COLUMNTYPE_LIST, self::COLUMNTYPE_SET => $this->packList($value, $subTypes),
            self::COLUMNTYPE_MAP => $this->packMap($value, $subTypes),
            self::COLUMNTYPE_UDT => $this->packUDT($value, $options['name'] ?? null, $subTypes),
            self::COLUMNTYPE_TUPLE => $this->packTuple($value, $subTypes),
            default => throw new \InvalidArgumentException('Unknown column type ' . $type)
        };
    }

    /**
     * Unpacks a value from its binary form based on a column type. Used for parsing rows.
     *
     * @param ?string $content Content to unpack.
     * @param int $type        Column type.
     * @param array $subTypes  The subtype for values that contain other values. This includes lists, maps and tuples
     *
     * @return mixed The unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackValue(?string $content, int $type, array $subTypes = [], array $options = []): mixed
    {
        if ($content === NULL) {
            return NULL;
        }

        return match ($type) {
            self::COLUMNTYPE_CUSTOM, self::COLUMNTYPE_BLOB => $this->unpackBlob($content),
            self::COLUMNTYPE_ASCII, self::COLUMNTYPE_TEXT, self::COLUMNTYPE_VARCHAR => $content,
            self::COLUMNTYPE_BIGINT, self::COLUMNTYPE_COUNTER, self::COLUMNTYPE_TIMESTAMP => $this->unpackBigint($content),
            self::COLUMNTYPE_BOOLEAN => $this->unpackBoolean($content),
            self::COLUMNTYPE_DECIMAL => $this->unpackDecimal($content),
            self::COLUMNTYPE_DOUBLE => $this->unpackDouble($content),
            self::COLUMNTYPE_FLOAT => $this->unpackFloat($content),
            self::COLUMNTYPE_INT => $this->unpackInt($content),
            self::COLUMNTYPE_UUID, self::COLUMNTYPE_TIMEUUID => $this->unpackUuid($content),
            self::COLUMNTYPE_VARINT => $this->unpackVarInt($content),
            self::COLUMNTYPE_INET => $this->unpackInet($content),
            self::COLUMNTYPE_LIST, self::COLUMNTYPE_SET => $this->unpackList($content, $subTypes[0]),
            self::COLUMNTYPE_MAP => $this->unpackMap($content, $subTypes[0], $subTypes[1]),
            self::COLUMNTYPE_UDT => $this->unpackUDT($content, $options['name'], $subTypes),
            self::COLUMNTYPE_TUPLE => $this->unpackTuple($content, $subTypes),
            default => throw new ProtocolException('Unknown column type returned from cassandra ' . $type)
        };
    }

    /**
     * Packs a COLUMNTYPE_BLOB value to its binary form.
     *
     * @param string $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packBlob(string $value): string
    {
        if (str_starts_with($value, '0x')) {
            $value = pack('H*', substr($value, 2));
        }
        return $value;
    }

    /**
     * Unpacks a COLUMNTYPE_BLOB value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return string Unpacked value in hexadecimal representation.
     */
    protected function unpackBlob(string $content, string $prefix = '0x'): string
    {
        $value = unpack('H*', $content);
        if ($value[1]) {
            $value[1] = $prefix . $value[1];
        }
        return $value[1];
    }

    /**
     * Packs a COLUMNTYPE_BIGINT value to its binary form.
     *
     * @param int $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packBigint(int $value): string
    {
        return $this->binFromInt($value, 8, true);
    }

    /**
     * Unpacks a COLUMNTYPE_BIGINT value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return int Unpacked value.
     */
    protected function unpackBigint(string $content): int
    {
        return $this->intFromBin($content, 0, 8, true);
    }

    /**
     * Packs a COLUMNTYPE_BOOLEAN value to its binary form.
     *
     * @param ?bool $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packBoolean(?bool $value): string
    {
        if ($value === NULL) {
            return '';
        }

        return ($value)? chr(1) : chr(0);
    }

    /**
     * Unpacks a COLUMNTYPE_BOOLEAN value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return ?bool Unpacked value.
     */
    protected function unpackBoolean(string $content): ?bool
    {
        if (strlen($content) > 0) {
            $c = ord($content[0]);
            if ($c == 1) {
                return true;
            } elseif ($c == 0) {
                return false;
            } else {
                return null;
            }
        }

        return null;
    }

    /**
     * Packs a COLUMNTYPE_DECIMAL value to its binary form.
     *
     * @param float|int $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packDecimal(float|int $value): string
    {
        // Based on http://docs.oracle.com/javase/7/docs/api/java/math/BigDecimal.html

        // Find the scale
        $value1 = abs($value);
        $positiveScale = 0;
        while (floor($value1) && (fmod($value1, 10) == 0)) {
            $value1 /= 10;
            $positiveScale++;
        }

        $value1 = $value;
        $negativeScale = 0;
        while (fmod($value1, 1)) {
            $value1 *= 10;
            $negativeScale--;
        }

        $scale = $negativeScale ? -$negativeScale : -$positiveScale;
        $unscaledValue = $value / pow(10, -$scale);

        return $this->packInt($scale) . $this->packVarInt($unscaledValue);
    }

    /**
     * Unpacks a COLUMNTYPE_DECIMAL value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return float|int Unpacked value.
     */
    protected function unpackDecimal(string $content): float|int
    {
        // Based on http://docs.oracle.com/javase/7/docs/api/java/math/BigDecimal.html

        $len = strlen($content);
        if ($len < 5) {
            return 0;
        }

        $data = unpack('N', $content);
        $scale = $data[1];
        $unscaledValue = $this->unpackVarInt(substr($content, 4));

        return $unscaledValue * pow(10, -$scale);
    }

    /**
     * Packs a COLUMNTYPE_DOUBLE value to its binary form.
     *
     * @param double $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packDouble(float $value): string
    {
        $littleEndian = pack('d', $value);
        $retval = '';
        for ($i = 7; $i >= 0; $i--) {
            $retval .= $littleEndian[$i];
        }
        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_DOUBLE value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return double Unpacked value.
     */
    protected function unpackDouble(string $content): float
    {
        $bigEndian = '';
        for ($i = 7; $i >= 0; $i--) {
            $bigEndian .= $content[$i];
        }

        $value = unpack('d', $bigEndian);
        return $value[1];
    }

    /**
     * Packs a COLUMNTYPE_FLOAT value to its binary form.
     *
     * @param float $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packFloat(float $value): string
    {
        $littleEndian = pack('f', $value);
        $retval = '';
        for ($i = 3; $i >= 0; $i--) {
            $retval .= $littleEndian[$i];
        }
        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_FLOAT value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return float Unpacked value.
     */
    protected function unpackFloat(string $content): float
    {
        $bigEndian = '';
        for ($i = 3; $i >= 0; $i--) {
            $bigEndian .= $content[$i];
        }

        $value = unpack('f', $bigEndian);
        return $value[1];
    }

    /**
     * Packs a COLUMNTYPE_INT value to its binary form.
     *
     * @param int $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packInt(int $value): string
    {
        return $this->binFromInt($value, 4, true);
    }

    /**
     * Unpacks a COLUMNTYPE_INT value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return int Unpacked value.
     */
    protected function unpackInt(string $content): int
    {
        return $this->intFromBin($content, 0, 4, true);
    }

    /**
     * Packs a COLUMNTYPE_UUID value to its binary form.
     *
     * @param string $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packUuid(string $value): string
    {
        return pack('H*', str_replace('-', '', $value));
    }

    /**
     * Unpacks a COLUMNTYPE_UUID value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return ?string Unpacked value.
     */
    protected function unpackUuid(string $content): ?string
    {
        $value = unpack('H*', $content);
        if ($value[1]) {
            return substr($value[1], 0, 8) . '-' . substr($value[1], 8, 4) . '-' .
                substr($value[1], 12, 4) . '-' . substr($value[1], 16, 4) . '-' .
                substr($value[1], 20);
        }

        return null;
    }

    /**
     * Packs a COLUMNTYPE_VARINT value to its binary form.
     *
     * @param int $content Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packVarInt(int $content): string
    {
        return $this->binFromInt($content, 0xFFFF, true);
    }

    /**
     * Unpacks a COLUMNTYPE_VARINT value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return int Unpacked value.
     */
    protected function unpackVarInt(string $content): int
    {
        return $this->intFromBin($content, 0, strlen($content), true);
    }

    /**
     * Packs a COLUMNTYPE_INET value to its binary form.
     *
     * @param string $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packInet(string $value): string
    {
        return inet_pton($value);
    }

    /**
     * Unpacks a COLUMNTYPE_INET value from its binary form.
     *
     * @param string $content Content to unpack.
     *
     * @return string Unpacked value.
     */
    protected function unpackInet(string $content): string
    {
        return inet_ntop($content);
    }

    /**
     * Packs a COLUMNTYPE_LIST value to its binary form.
     *
     * @param ListType $list  Value to pack.
     * @param array $subTypes List containing the type of the List or Sets Values. Only provided if used within a
     *                        Prepared Statement
     *
     * @return string Binary form of the value.
     *
     * @throws \InvalidArgumentException
     * @throws CassandraException
     */
    protected function packList(ListType $list, array $subTypes): string
    {
        $retval = $this->packInt(count($list));
        $listType = $list->getType();

        // Prepared statements return the type of the list or set. We can
        // use this to check if the user provided the right types upfront
        if (!empty($subTypes) && $listType !== $subTypes[0]) {
            throw new QueryException(
                sprintf(
                    "List/Set type doesn't match the type expected by Cassandra. Expected %s got %s",
                    $this->typeToString($subTypes[0]),
                    $this->typeToString($listType)
                )
            );
        }

        foreach ($list as $item) {
            $itemPacked = $this->packValue($item, $listType);
            $retval .= $this->packLongString($itemPacked);
        }

        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_LIST value from its binary form.
     *
     * @param string $content Content to unpack.
     * @param int $subtype    Type of the List or Sets items
     *
     * @return ListType Unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackList(string $content, int $subtype): ListType
    {
        $contentOffset = 0;
        $itemsCount = $this->popInt($content, $contentOffset);
        $retval = [];

        for (; $itemsCount; $itemsCount--) {
            $subcontent = $this->popLongString($content, $contentOffset);
            $retval[] = $this->unpackValue($subcontent, $subtype);
        }

        $listFactory = new ListFactory($subtype);
        return $listFactory->create($retval);
    }

    /**
     * Packs a COLUMNTYPE_MAP value to its binary form.
     *
     * @param MapType $map    Value to pack.
     * @param array $subTypes List containing the type of the Maps keys and Values. Only provided if used within a
     *                        Prepared Statement
     *
     * @return string Binary form of the value.
     *
     * @throws \InvalidArgumentException
     * @throws CassandraException
     */
    protected function packMap(MapType $map, array $subTypes): string
    {
        $retval = $this->packInt(count($map));
        $keyType = $map->getKeyType();
        $valueType = $map->getValueType();

        // Prepared statements return the key and value type so we can 
        // check if the user has provided the correct types upfront
        if (!empty($subTypes)) {
            $actualKeyType = $subTypes[0];
            $actualValueType = $subTypes[1];

            if ($keyType !== $actualKeyType) {
                throw new QueryException(
                    sprintf(
                        "Map key type doesn't match the type expected by Cassandra. Expected %s got %s",
                        $this->typeToString($actualValueType),
                        $this->typeToString($valueType)
                    )
                );
            }

            if ($valueType !== $actualValueType) {
                throw new QueryException(
                    sprintf(
                        "Map value type doesn't match the type expected by Cassandra. Expected %s got %s",
                        $this->typeToString($actualValueType),
                        $this->typeToString($valueType)
                    )
                );
            }
        }

        foreach ($map as $key => $item) {
            $keyPacked = $this->packValue($key, $keyType);
            $itemPacked = $this->packValue($item, $valueType);
            $retval .= $this->packLongString($keyPacked) .
                $this->packLongString($itemPacked);
        }

        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_MAP value from its binary form.
     *
     * @param string $content Content to unpack.
     * @param int $keyType    Type of the Maps keys
     * @param int $valueType  Type of the Maps values
     *
     * @return MapType Unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackMap(string $content, int $keyType, int $valueType): MapType
    {
        $contentOffset = 0;
        $itemsCount = $this->popInt($content, $contentOffset);
        $retval = [];

        for (; $itemsCount; $itemsCount--) {
            $subKeyRaw = $this->popLongString($content, $contentOffset);
            $subValueRaw = $this->popLongString($content, $contentOffset);

            $subKey = $this->unpackValue($subKeyRaw, $keyType);
            $subValue = $this->unpackValue($subValueRaw, $valueType);
            $retval[$subKey] = $subValue;
        }

        $mapFactory = new MapFactory($keyType, $valueType);
        return $mapFactory->create($retval);
    }

    /**
     * Packs a COLUMNTYPE_UDT value to its binary form.
     *
     * @param UDTType $udt    Value to pack
     * @param ?string $name   The name of the UDT. Only known if used within a prepared statement
     * @param array $subTypes List of pairs of the field and type of the UDT. Only known if used within a prepared
     *                        Statement
     *
     * @return string
     *
     * @throws CassandraException
     */
    protected function packUDT(UDTType $udt, ?string $name, array $subTypes): string
    {
        $retval = '';
        $types = $udt->getFields();

        if ($name !== null) {
            if ($udt->getName() !== $name) {
                throw new QueryException("Name of UDT doesn't match the name expected by Cassandra. Expected $name, got {$udt->getName()}");
            }

            if ($types !== $subTypes) {
                $expected = [];
                $actual = [];

                foreach ($subTypes as $field => $type) {
                    $expected[] = "$field <{$this->typeToString($type)}>";
                }

                foreach ($types as $field => $type) {
                    $actual[] = "$field <{$this->typeToString($type)}>";
                }

                throw new QueryException(
                    sprintf(
                        "UDT doesn't match the format expected by Cassandra. Expected %s, got %s",
                        implode(', ', $expected),
                        implode(', ', $actual)
                    )
                );
            }
        }

        foreach ($udt as $field => $value) {
            $type = $types[$field];
            $retval .= $this->packValue($value, $type);
        }

        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_UDT from its binary form
     *
     * @param string $content
     * @param string $name
     * @param array $fields
     *
     * @return UDTType
     *
     * @throws CassandraException
     */
    protected function unpackUDT(string $content, string $name, array $fields): UDTType
    {
        $contentOffset = 0;
        $values = [];

        foreach ($fields as $type) {
            $valueRaw = $this->popLongString($content, $contentOffset);
            $values[] = $this->unpackValue($valueRaw, $type);
        }

        $udtFactory = new UDTFactory($name, $fields);
        return $udtFactory->create($values);
    }

    /**
     * Packs a COLUMNTYPE_TUPLE value to its binary form.
     *
     * @param TupleType $tuple The value to pack
     * @param array $subTypes  List of types for each item within the Tuple. Only available if used within a Prepared
     *                         Statement
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     * @throws CassandraException
     */
    protected function packTuple(TupleType $tuple, array $subTypes): string
    {
        $retval = '';
        $types = $tuple->getTypes();

        // Prepared statements return the types of the tuple in the
        // correct order. We can use this to check the user is sending
        // the right types in the right order upfront
        if (!empty($subTypes) && $types !== $subTypes) {
            $typesStr = array_map([$this, 'typeToString'], $types);
            $subTypesStr = array_map([$this, 'typeToString'], $subTypes);

            throw new QueryException(
                sprintf(
                    "Tuple values doesn't match the format expected by Cassandra. Expected %s, got %s",
                    implode(', ', $subTypesStr),
                    implode(', ', $typesStr)
                )
            );
        }

        foreach ($tuple as $i => $value) {
            $packedValue = $this->packValue($value, $types[$i]);
            $retval .= $this->packLongString($packedValue);
        }

        return $retval;
    }

    /**
     * Unpacks a COLUMNTYPE_TUPLE from its binary form
     *
     * @param string $content
     * @param array $subTypes List of types for each item within the
     *                        Tuple
     *
     * @return TupleType
     *
     * @throws \InvalidArgumentException
     * @throws CassandraException
     */
    protected function unpackTuple(string $content, array $subTypes): TupleType
    {
        $contentOffset = 0;
        $values = [];

        foreach ($subTypes as $type) {
            $valueRaw = $this->popLongString($content, $contentOffset);
            $values[] = $this->unpackValue($valueRaw, $type);
        }

        $tupleFactory = new TupleFactory($subTypes);
        return $tupleFactory->create($values);
    }

    /**
     * @param string $content
     * @return array
     */
    protected function unpackMultimap(string $content): array
    {
        $currentOffset = 0;
        $itemsCount = $this->popShort($content, $currentOffset);

        $retval = [];
        for (; $itemsCount; $itemsCount--) {
            $subKey = $this->popString($content, $currentOffset);
            $subValueCount = $this->popShort($content, $currentOffset);
            $subValues = [];

            for (; $subValueCount; $subValueCount--) {
                $subValues[] = $this->popString($content, $currentOffset);
            }

            $retval[$subKey] = $subValues;
        }

        return $retval;
    }

    /**
     * Pops a [bytes] value from the body, starting from the offset, and advancing it in the process.
     *
     * @param string $body Content's body.
     * @param int &$offset Offset to start from.
     *
     * @return ?string Bytes content or null
     */
    protected function popBytes(string $body, int &$offset): ?string
    {
        $stringLength = $this->intFromBin($body, $offset, 4, true);

        // If the length of a returned bytes block is < 0, the represented value is null
        if ($stringLength < 0) {
            $offset += 4;
            return null;
        }

        $retval = substr($body, $offset + 4, $stringLength);
        $offset += $stringLength + 4;

        return $retval;
    }

    /**
     * Pops a [string] value from the body, starting from the offset, and
     * advancing it in the process.
     *
     * @param string $body Content's body.
     * @param int &$offset Offset to start from.
     *
     * @return ?string String content.
     */
    protected function popString(string $body, int &$offset): ?string
    {
        $len = substr($body, $offset, 2);
        if (strlen($len) < 2) {
            return null;
        }

        $stringLength = unpack('n', substr($body, $offset, 2));
        if ($stringLength[1] == 0xFFFF) {
            $offset += 2;
            return null;
        }

        $retval = substr($body, $offset + 2, $stringLength[1]);
        $offset += $stringLength[1] + 2;
        return $retval;
    }

    /**
     * Pops a [long string] value from the body, starting from the offset, and advancing it in the process.
     *
     * @param string $body Content's body.
     * @param int &$offset Offset to start from.
     *
     * @return ?string Long String content.
     */
    protected function popLongString(string $body, int &$offset): ?string
    {
        $stringLength = unpack('N', substr($body, $offset, 4));
        if ($stringLength[1] == 0xFFFFFFFF) {
            $offset += 4;
            return null;
        }

        $retval = substr($body, $offset + 4, $stringLength[1]);
        $offset += $stringLength[1] + 4;
        return $retval;
    }

    /**
     * Pops a [int] value from the body, starting from the offset, and advancing it in the process.
     *
     * @param string $body Content's body.
     * @param int &$offset Offset to start from.
     *
     * @return int Int content.
     */
    protected function popInt(string $body, int &$offset): int
    {
        $retval = $this->intFromBin($body, $offset, 4, true);
        $offset += 4;
        return $retval;
    }

    /**
     * Pops a [short] value from the body, starting from the offset, and advancing it in the process.
     *
     * @param string $body Content's body.
     * @param int &$offset Offset to start from.
     *
     * @return int Short content.
     */
    protected function popShort(string $body, int &$offset): int
    {
        $retval = $this->intFromBin($body, $offset, 2, true);
        $offset += 2;
        return $retval;
    }

    /**
     * Packs an outgoing frame.
     *
     * @param int $opcode   Frame's opcode.
     * @param string $body  Frame's body.
     * @param int $response Frame's response flag.
     * @param int $stream   Frame's stream id.
     *
     * @return string Frame's content.
     *
     * @throws CassandraException
     */
    protected function packFrame(int $opcode, string $body = '', int $response = 0, int $stream = 0): string
    {
        $version = ($response << 0x07) | self::PROTOCOL_VERSION;
        $flags = 0;

        // STARTUP and OPTION Messages cannot be compressed
        $invalidOpcodes = [self::OPCODE_STARTUP, self::OPCODE_OPTIONS];
        if (!in_array($opcode, $invalidOpcodes) && $this->compressor instanceof CompressorInterface) {
            $flags |= self::FLAG_COMPRESSION;
            if (($body = $this->compressor->compress($body)) === false) {
                throw new CompressionException('Could not compress request to send to Cassandra');
            }
        }

        return pack(
            'CCnCNa*',
            $version,
            $flags,
            $stream,
            $opcode,
            strlen($body),
            $body
        );
    }

    /**
     * Packs a [long string] notation (section 3)
     *
     * @param string $data String content.
     *
     * @return string Data content.
     */
    protected function packLongString(string $data): string
    {
        return pack('Na*', strlen($data), $data);
    }

    /**
     * Packs a [string] notation (section 3)
     *
     * @param string $data String content.
     *
     * @return string Data content.
     */
    protected function packString(string $data): string
    {
        return pack('na*', strlen($data), $data);
    }

    /**
     * Packs a [short] notation (section 3)
     *
     * @param int $data Short content.
     *
     * @return string Data content.
     */
    protected function packShort(int $data): string
    {
        return chr($data >> 0x08) . chr($data & 0xFF);
    }

    /**
     * Packs a [short] notation (missing from specs)
     *
     * @param int $data Byte content.
     *
     * @return string Data content.
     */
    protected function packByte(int $data): string
    {
        return chr($data);
    }

    /**
     * Packs a [string map] notation (section 3)
     *
     * @param array $dataArr Associative array of the map.
     *
     * @return string Data content.
     */
    protected function packStringMap(array $dataArr): string
    {
        $retval = pack('n', count($dataArr));
        foreach ($dataArr as $key => $value) {
            $retval .= $this->packString($key) . $this->packString($value);
        }
        return $retval;
    }

    /**
     * Converts binary format to a varint.
     *
     * @param string $data Binary content.
     * @param int $offset  Starting data offset.
     * @param int $length  Data length.
     * @param bool $signed Whether the returned data can be signed.
     *
     * @return int Parsed varint.
     */
    protected function intFromBin(string $data, int $offset, int $length, bool $signed = false): int
    {
        $len = strlen($data);

        if ((!$length) || ($offset >= $len)) {
            return 0;
        }

        $signed = $signed && (ord($data[$offset]) & 0x80);

        $value = 0;
        for ($i = 0; $i < $length; $i++) {
            $v = ord($data[$i + $offset]);
            if ($signed) {
                $v ^= 0xFF;
            }
            $value = $value * 256 + $v;
        }

        if ($signed) {
            $value = -($value + 1);
        }

        return $value;
    }

    /**
     * Converts varint to its binary format.
     *
     * @param int $value   Binary content.
     * @param int $length  Data length.
     * @param bool $signed Whether the returned data can be signed.
     *
     * @return string Binary content.
     */
    protected function binFromInt(int $value, int $length, bool $signed = false): string
    {
        $negative = (($signed) && ($value < 0));
        if ($negative) {
            $value = -($value + 1);
        }

        $retval = '';
        for ($i = 0; $i < $length; $i++) {
            $v = $value % 256;
            if ($negative) {
                $v ^= 0xFF;
            }
            $retval = chr($v) . $retval;
            $value = floor($value / 256);

            if (($length == 0xFFFF) && ($value == 0)) {
                break;
            }
        }

        return $retval;
    }

    /**
     * Returns the string name for an internal Cassandra Type
     *
     * @param int $type
     *
     * @return string
     */
    protected function typeToString(int $type): string
    {
        return match ($type) {
            self::COLUMNTYPE_CUSTOM => 'custom',
            self::COLUMNTYPE_BLOB => 'blob',
            self::COLUMNTYPE_ASCII => 'ascii',
            self::COLUMNTYPE_TEXT => 'text',
            self::COLUMNTYPE_VARCHAR => 'varchar',
            self::COLUMNTYPE_BIGINT => 'bigint',
            self::COLUMNTYPE_COUNTER => 'counter',
            self::COLUMNTYPE_TIMESTAMP => 'timestamp',
            self::COLUMNTYPE_BOOLEAN => 'boolean',
            self::COLUMNTYPE_DECIMAL => 'decimal',
            self::COLUMNTYPE_DOUBLE => 'double',
            self::COLUMNTYPE_FLOAT => 'float',
            self::COLUMNTYPE_INT => 'int',
            self::COLUMNTYPE_UUID => 'uuid',
            self::COLUMNTYPE_TIMEUUID => 'timeuuid',
            self::COLUMNTYPE_VARINT => 'varint',
            self::COLUMNTYPE_INET => 'inet',
            self::COLUMNTYPE_LIST => 'list',
            self::COLUMNTYPE_SET => 'set',
            self::COLUMNTYPE_MAP => 'map',
            self::COLUMNTYPE_UDT => 'udt',
            self::COLUMNTYPE_TUPLE => 'tuple',
            default => 'Unknown column type ' . $type
        };
    }
}
