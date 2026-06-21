<?php

namespace CassandraNative;

use CassandraNative\Auth\AuthChallengeProviderInterface;
use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Compression\CompressorInterface;
use CassandraNative\Connection\Socket;
use CassandraNative\Exception\AuthenticationException;
use CassandraNative\Exception\CassandraException;
use CassandraNative\Exception\ClientException;
use CassandraNative\Exception\CompressionException;
use CassandraNative\Exception\ConnectionException;
use CassandraNative\Exception\ProtocolException;
use CassandraNative\Exception\QueryException;
use CassandraNative\Result\Rows;
use CassandraNative\Statement\PreparedStatement;
use CassandraNative\Statement\SimpleStatement;
use CassandraNative\Statement\StatementInterface;

/**
 * Cassanda Connector
 *
 * A native Cassandra connector for PHP based on the CQL binary protocol v3,
 * without the need for any external extensions.
 *
 * Requires PHP version >8.2, and Cassandra >1.2.
 *
 * Usage and more information is found on README.md
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2023 Uri Hartmann
 * Copyright (c) 2026 Christopher Birmingham
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
    protected const FLAG_COMPRESSION    = 0x01;
    protected const FLAG_TRACING        = 0x02;
    protected const FLAG_CUSTOM_PAYLOAD = 0x04;
    protected const FLAG_WARNING        = 0x08;

    protected const PROTOCOL_VERSION = 4;

    protected const MAX_STREAM_ID = 32768;

    /**
     * @param Socket $socket
     * @param Consistency $defaultConsistency
     * @param ?CompressorInterface $compressor
     * @param ?AuthProviderInterface $authProvider
     * @param bool $usingPersistence
     * @throws CassandraException
     */
    public function __construct(
        protected Socket $socket,
        protected Consistency $defaultConsistency,
        protected ?CompressorInterface $compressor,
        protected ?AuthProviderInterface $authProvider,
        protected bool $usingPersistence,
        protected bool $throwOnOverload
    ) {
        $this->establishConnection();
    }

    /**
     * Establishes a connection with a cassandra host based on options provided
     * by the ClusterBuilder
     * 
     * @throws CassandraException
     */
    protected function establishConnection(): void
    {
        // Don't send options, startup & authentication if we're on a persistent
        // connection as we've already done that work before
        if ($this->socket->isPersistent()) {
            return;
        }

        // Send an OPTIONS request and check our clients compatibility
        $optionsMap = $this->sendOptionsFrame();
        $this->checkCompatibility($optionsMap);

        // Now we're compatible, lets be friends
        $this->sendStartupFrame();
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
        $this->writeFrame(Opcode::Options);
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
        $body = $this->readFrame([Opcode::Supported])[1];
        return $this->unpackMultimap($body);
    }

    /**
     * Checks if the client and the connected cassandra node support the same options
     *
     * @param array $optionsMap The options map from the cluster to check against
     *
     * @throws CassandraException
     */
    protected function checkCompatibility(array $optionsMap): void
    {
        $version = self::PROTOCOL_VERSION . '/v' . self::PROTOCOL_VERSION;

        if (!in_array($version, $optionsMap['PROTOCOL_VERSIONS'])) {
            throw new ClientException(
                sprintf(
                    'Client configured to use protocol %s but Cassandra Cluster supports %s',
                    self::PROTOCOL_VERSION,
                    implode(', ', $optionsMap['PROTOCOL_VERSIONS'])
                )
            );
        }

        $supportedCompressors = $optionsMap['COMPRESSION'] ?? [];

        if (empty($supportedCompressors)) {
            throw new ClientException(
                'Client configured to use compression but connected Cassandra Cluster only supports uncompressed communication'
            );
        }

        if (!in_array($this->compressor->getName(), $supportedCompressors)) {
            throw new ClientException(
                sprintf(
                    'Client configured to use %s compression but Cassandra Cluster supports %s compression',
                    $this->compressor->getName(),
                    implode(', ', $supportedCompressors)
                )
            );
        }
    }

    /**
     * Sends a STARTUP frame to the cassandra node.
     *
     * @throws CassandraException
     */
    protected function sendStartupFrame(): void
    {
        $startBody = [
            'CQL_VERSION' => '3.0.0',
            'DRIVER_NAME' => 'PHP Cassandra Native Driver',
            'DRIVER_VERSION' => '4.0.0'
        ];

        if ($this->compressor instanceof CompressorInterface) {
            $startBody['COMPRESSION'] = $this->compressor->getName();
        }

        if ($this->throwOnOverload) {
            $startBody['THROW_ON_OVERLOAD'] = '1';
        }

        // Writes a STARTUP frame
        $frameBody = $this->packStringMap($startBody);
        $this->writeFrame(Opcode::Startup, $frameBody);

        $this->startupResult();
    }

    /**
     * Retrieves the result of a STARTUP request
     *
     * @throws CassandraException
     */
    protected function startupResult(): void
    {
        list($opcode, $body) = $this->readFrame([Opcode::Ready, Opcode::Authenticate]);

        if ($opcode === Opcode::Ready) {
            if ($this->authProvider instanceof AuthProviderInterface) {
                throw new ConnectionException("Client is configured with an auth provider but Cassandra didn't issue an auth challenge");
            }

            return;
        }

        $offset = 0;
        $this->handleAuth($this->popString($body, $offset));
    }

    /**
     * Respond to an authentication challenge issued by the cassandra node
     *
     * @param string $authMechanism
     *
     * @throws CassandraException
     */
    protected function handleAuth(string $authMechanism): void
    {
        if (!($this->authProvider instanceof AuthProviderInterface)) {
            throw new AuthenticationException('Cassandra sent an auth challenge but an Authentication provider was not provided');
        }

        if ($this->authProvider->mechanism() !== $authMechanism) {
            throw new AuthenticationException("Cassandra sent back an auth challenge for $authMechanism which doesn't match the one the client is configured for");
        }

        $authResponseBody = $this->authProvider->response();
        $authResponseBody = $this->packLongString($authResponseBody);
        $this->writeFrame(Opcode::AuthResponse, $authResponseBody);

        list($opcode, $body) = $this->readFrame([Opcode::AuthSuccess, Opcode::AuthChallenge]);

        if ($opcode === Opcode::AuthChallenge) {
            if (!($this->authProvider instanceof AuthChallengeProviderInterface)) {
                throw new AuthenticationException("Cassandra issued a challenge response but provider doesn't support challenges");
            }

            // @todo This code could infinite loop. Possibly add a challenge limit
            do {
                $authChallengeResponseBody = $this->authProvider->challengeResponse($body);
                $authChallengeResponseBody = $this->packLongString($authChallengeResponseBody);
                $this->writeFrame(Opcode::AuthResponse, $authChallengeResponseBody);

                list($opcode, $body) = $this->readFrame([Opcode::AuthSuccess, Opcode::AuthChallenge]);
            } while ($opcode == Opcode::AuthChallenge);
        }
    }

    /**
     * Connects the client to the specified keyspace. Same as using a 
     * USE $keyspace query
     * 
     * @param string $keyspace
     * 
     * @throws CassandraException
     */
    public function connect(string $keyspace): void
    {
        $stmt = new SimpleStatement("USE $keyspace");
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
     * @param StatementInterface $stmt  The query to run.
     * @param array $values             Values to bind in a sequential or key=>value format, where key is the column's name.
     * @param ?Consistency $consistency Consistency level for the operation. Defaults to default configured consistency
     *
     * @return Rows Result of the query. Might be an array of rows (for
     *              SELECT), or the operation's result (for USE, CREATE,
     *              ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    public function execute(
        StatementInterface $stmt,
        array $values = [],
        ?Consistency $consistency = null
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
        $retval = $this->requestResult(Opcode::Prepare, $frame);

        return new PreparedStatement($retval['id'], $retval['columns']);
    }

    /**
     * Executes a prepared statement.
     *
     * @param PreparedStatement $stmt  The prepared statement as returned from the
     *                                 prepare method.
     * @param array $values            Bind values for the prepared statement
     * @param Consistency $consistency Consistency level for the operation.
     *
     * @return array Result of the execution. Might be an array of rows (for
     *               SELECT), or the operation's result (for USE, CREATE,
     *               ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    protected function executePreparedStatement(
        PreparedStatement $stmt,
        array $values,
        Consistency $consistency
    ): array {
        // Prepares the frame's body - <id><count><values map>
        $frame = [
            $this->packString(base64_decode($stmt->id)),
            $this->packShort($consistency->value) .
            $this->packByte(0x01) . // values only
            $this->packShort(count($values))
        ];

        foreach ($stmt->columns as $key => $column) {
            if (!isset($values[$key])) {
                throw new QueryException("Missing value for bound parameter $key");
            }

            $value = $values[$key];

            $data = $this->packValue(
                $value,
                $column['type'],
                $column['subtypes']
            );

            $frame[] = $this->packLongString($data);
        }

        // Writes a EXECUTE frame and return the result
        return $this->requestResult(Opcode::Execute, implode($frame));
    }

    /**
     * Executes a simple statement
     * 
     * @param SimpleStatement $stmt    The statement to be run.
     * @param array $values            Values to bind to the statement being run.
     * @param Consistency $consistency Consistency level for the operation.
     * 
     * @return array Result of the execution. Might be an array of rows (for
     *               SELECT), or the operation's result (for USE, CREATE,
     *               ALTER, UPDATE).
     * 
     * @throws CassandraException
     */
    protected function executeSimpleStatement(
        SimpleStatement $stmt,
        array $values,
        Consistency $consistency
    ): array {
        // Prepares the frame's body
        // TODO: Support the new <flags> byte
        $frame = [
            $this->packLongString($stmt->getStatement()),
            $this->packShort($consistency->value)
        ];

        if (count($values)) {
            $valuesData = '';
            $namedParameters = !array_is_list($values);

            foreach ($values as $key => $value) {
                if ($namedParameters) {
                    $valuesData .= $this->packString($key);
                }

                if (!is_array($value) || count($value) != 2) {
                    throw new \InvalidArgumentException('Value must be an array of 2 items');
                }

                $type = $value[1];

                if (!($type instanceof ColumnType)) {
                    throw new \InvalidArgumentException("Invalid field type provided for column $key. Must be one of type ColumnType");
                }

                if (in_array($type, [ColumnType::List, ColumnType::Set, ColumnType::Map, ColumnType::Udt, ColumnType::Tuple])) {
                    throw new \InvalidArgumentException('Container types are not supported with SimpleStatements');
                }

                $data = $this->packValue($value[0], $type);
                $valuesData .= $this->packLongString($data);
            }

            array_push(
                $frame,
                $this->packByte(0x01 | ($namedParameters ? 0x40 : 0x00)),
                $this->packShort(count($values)),
                $valuesData
            );
        } else {
            $frame[] = $this->packByte(0x00);
        }

        return $this->requestResult(Opcode::Query, implode($frame));
    }

    /**
     * Writes a (QUERY/PREPARE/EXCUTE) frame, reads the result, and parses it.
     *
     * @param Opcode $opcode Frame's opcode.
     * @param string $body   Frame's body.
     *
     * @return array Result of the request. Might be an array of rows (for
     *               SELECT), or the operation's result (for USE, CREATE,
     *               ALTER, UPDATE).
     *
     * @throws CassandraException
     */
    protected function requestResult(Opcode $opcode, string $body): array
    {
        $requestStreamId = ($this->usingPersistence) ? rand(1, self::MAX_STREAM_ID) : 0;

        // Writes the frame
        $this->writeFrame($opcode, $body, $requestStreamId);

        // Reads incoming frame
        $body = $this->readFrame([Opcode::Result], $requestStreamId)[1];
        return $this->parseResult($body);
    }

    /**
     * Packs and writes a frame to the socket.
     *
     * @param Opcode $opcode Frame's opcode.
     * @param string $body   Frame's body.
     * @param int $stream    Frame's stream id.
     *
     * @throws CassandraException
     */
    protected function writeFrame(Opcode $opcode, string $body = '', int $stream = 0): void
    {
        // Prepares the outgoing packet
        $frame = $this->packFrame($opcode, $body, $stream);

        // Writes frame to socket
        $this->socket->write($frame);
    }

    /**
     * Parses a returned Frame send back by cassandra. Checks for any errors returned 
     * and throws an exception
     * 
     * @param string $header            The returned frame header
     * @param string $body              The returned frame body
     * @param Opcode[] $expectedOpcodes The expected opcodes that Cassandra should have returned
     * 
     * @return array{Opcode, string} The parsed frame converted into an opcode and body
     * 
     * @throws CassandraException
     */
    protected function parseIncomingFrame(string $header, string $body, array $expectedOpcodes): array
    {
        $flags = ord($header[1]);

        // Unpack the header to its contents:
        // <byte version><byte flags><uint16 stream><byte opcode><int length>

        try {
            $opcode = Opcode::from(ord($header[4]));
        } catch (\ValueError) {
            throw new ProtocolException('Unknown opcode returned from Cassandra');
        }

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
                trigger_error("Warning returned while processing Cassandra query: $warning", E_USER_WARNING);
            }

            $body = substr($body, $iPos);
        }

        // If we got an error - trigger it and return an error
        if ($opcode == Opcode::Error) {
            // ERROR: <int code><string msg>
            $errCode = $this->intFromBin($body, 0, 4);
            $bodyOffset = 4;  // Must be passed by reference
            $errMsg = $this->popString($body, $bodyOffset);
            ErrorCode::from($errCode)->toException($errMsg);
        } elseif (!in_array($opcode, $expectedOpcodes)) {
            $expected = implode(' or ', array_map(fn(Opcode $op) => $op->toString(), $expectedOpcodes));
            throw new ProtocolException("Missing $expected packet. Got {$opcode->toString()} instead", $opcode);
        }

        return [$opcode, $body];
    }

    /**
     * Reads pending frame from the socket.
     *
     * @param Opcode[] $expectedOpcodes The expected opcodes that Cassandra should have returned
     * @param int $requestStreamId      The stream id we're expecting to get back
     *
     * @return array{Opcode, string}    Incoming data.
     *
     * @throws CassandraException
     */
    protected function readFrame(array $expectedOpcodes, int $requestStreamId = 0): array
    {
        /**
         * If a php thread using a persistent connection fatals before reading the response from Cassandra,
         * the next thread to read from that connection will read the old response. Check to see if the responses
         * stream id matches the one we're expecting and discard any orphaned responses.
         */
        do {
            // Read the 9 bytes header
            $header = $this->socket->read(9);
            $responseStreamId = $this->intFromBin($header, 2, 2);
            $length = $this->intFromBin($header, 5, 4);

            // Read frame body, if exists
            $body = ($length) ? $this->socket->read($length) : '';
        } while ($requestStreamId !== $responseStreamId);

        return $this->parseIncomingFrame($header, $body, $expectedOpcodes);
    }

    /**
     * Parses a RESULT frame.
     *
     * @param string $body Frame's body
     *
     * @return array       Parsed frame. Might be an array of rows (for SELECT),
     *                     or the operation's result (for USE, CREATE, ALTER,
     *                     UPDATE).
     *
     * @throws CassandraException
     */
    protected function parseResult(string $body): array
    {
        // Parse RESULTS opcode
        $bodyOffset = 0;
        $kind = $this->popInt($body, $bodyOffset);

        switch (ResultKind::tryFrom($kind)) {
            case ResultKind::Void:
                return [['result' => 'success']];
            case ResultKind::Rows:
                return $this->parseRows($body, $bodyOffset);
            case ResultKind::SetKeyspace:
                $keyspace = $this->popString($body, $bodyOffset);
                return [['keyspace' => $keyspace]];
            case ResultKind::Prepared:
                // <string id><metadata>
                $id = base64_encode($this->popString($body, $bodyOffset));
                $metadata = $this->parseRowsMetadata($body, $bodyOffset, true);
                $columns = [];

                foreach ($metadata as $column) {
                    $columns[$column['name']] = [
                        'type' => $column['type'],
                        'subtypes' => $column['subtypes']
                    ];
                }

                return [
                    'id' => $id,
                    'columns' => $columns
                ];
            case ResultKind::SchemaChange:
                // <string change><string keyspace><string table>
                $change = $this->popString($body, $bodyOffset);
                $target = $this->popString($body, $bodyOffset);
                $options = $this->popString($body, $bodyOffset);
                return [[
                    'change' => $change,
                    'target' => $target,
                    'options' => $options
                ]];
            default:
                throw new ProtocolException("Unknown result kind $kind returned");
        }
    }

    /**
     * Parses a RESULT Rows metadata (also used for RESULT Prepared), starting
     * from the offset, and advancing it in the process.
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
            $columnType = ColumnType::from($this->popShort($body, $bodyOffset));
            $subTypes = [];

            switch ($columnType) {
                case ColumnType::List:
                case ColumnType::Set:
                    $subTypes = [ColumnType::from($this->popShort($body, $bodyOffset))];
                    break;
                case ColumnType::Map:
                    $subTypes = [
                        ColumnType::from($this->popShort($body, $bodyOffset)),
                        ColumnType::from($this->popShort($body, $bodyOffset))
                    ];
                    break;
                case ColumnType::Udt:
                    $this->popString($body, $bodyOffset); // Skip over keyspace
                    $this->popString($body, $bodyOffset); // Skip over name
                    $itemCount = $this->popShort($body, $bodyOffset);

                    for (; $itemCount; $itemCount--) {
                        $name = $this->popString($body, $bodyOffset);
                        $value = $this->popShort($body, $bodyOffset);
                        $subTypes[$name] = ColumnType::from($value);
                    }
                    break;
                case ColumnType::Tuple:
                    $itemCount = $this->popShort($body, $bodyOffset);

                    for (; $itemCount; $itemCount--) {
                        $subTypes[] = ColumnType::from($this->popShort($body, $bodyOffset));
                    }
            }

            $columns[] = [
                'keyspace' => $keyspace,
                'table' => $table,
                'name' => $columnName,
                'type' => $columnType,
                'subtypes' => $subTypes
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
                $value = $this->unpackValue($content, $col['type'], $col['subtypes']);
                $row[$col['name']] = $value;
            }
            $retval[] = $row;
        }

        return $retval;
    }

    /**
     * Packs a value to its binary form based on a column type. Used for
     * prepared statement.
     *
     * @param mixed $value           Value to pack
     * @param ColumnType $type       Column type
     * @param ColumnType[] $subTypes List of subtypes for container types
     *
     * @return string Binary form of the value.
     */
    protected function packValue(
        mixed $value,
        ColumnType $type,
        array $subTypes = []
    ): string {
        return match ($type) {
            ColumnType::Custom, ColumnType::Blob => $this->packBlob($value),
            ColumnType::Ascii, ColumnType::Text, ColumnType::Varchar => $value,
            ColumnType::Bigint, ColumnType::Counter, ColumnType::Timestamp => $this->packBigint($value),
            ColumnType::Boolean => $this->packBoolean($value),
            ColumnType::Decimal => $this->packDecimal($value),
            ColumnType::Double => $this->packDouble($value),
            ColumnType::Float => $this->packFloat($value),
            ColumnType::Int => $this->packInt($value),
            ColumnType::Uuid, ColumnType::Timeuuid => $this->packUuid($value),
            ColumnType::Varint => $this->packVarInt($value),
            ColumnType::Inet => $this->packInet($value),
            ColumnType::List, ColumnType::Set => $this->packList($value, $subTypes[0]),
            ColumnType::Map => $this->packMap($value, $subTypes[0], $subTypes[1]),
            ColumnType::Udt => $this->packUDT($value, $subTypes),
            ColumnType::Tuple => $this->packTuple($value, $subTypes)
        };
    }

    /**
     * Unpacks a value from its binary form based on a column type. Used for
     * parsing rows.
     *
     * @param ?string $content       Content to unpack.
     * @param ColumnType $type       Column type.
     * @param ColumnType[] $subTypes List of subtypes for container types
     *
     * @return mixed The unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackValue(
        ?string $content,
        ColumnType $type,
        array $subTypes = []
    ): mixed {
        if ($content === NULL) {
            return NULL;
        }

        return match ($type) {
            ColumnType::Custom, ColumnType::Blob => $this->unpackBlob($content),
            ColumnType::Ascii, ColumnType::Text, ColumnType::Varchar => $content,
            ColumnType::Bigint, ColumnType::Counter, ColumnType::Timestamp => $this->unpackBigint($content),
            ColumnType::Boolean => $this->unpackBoolean($content),
            ColumnType::Decimal => $this->unpackDecimal($content),
            ColumnType::Double => $this->unpackDouble($content),
            ColumnType::Float => $this->unpackFloat($content),
            ColumnType::Int => $this->unpackInt($content),
            ColumnType::Uuid, ColumnType::Timeuuid => $this->unpackUuid($content),
            ColumnType::Varint => $this->unpackVarInt($content),
            ColumnType::Inet => $this->unpackInet($content),
            ColumnType::List, ColumnType::Set => $this->unpackList($content, $subTypes[0]),
            ColumnType::Map => $this->unpackMap($content, $subTypes[0], $subTypes[1]),
            ColumnType::Udt => $this->unpackUDT($content, $subTypes),
            ColumnType::Tuple => $this->unpackTuple($content, $subTypes)
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
     * @param bool $value Value to pack.
     *
     * @return string Binary form of the value.
     */
    protected function packBoolean(bool $value): string
    {
        return chr($value ? 1 : 0);
    }

    /**
     * Unpacks a COLUMNTYPE_BOOLEAN value from its binary form.
     * Cassandra docs say to tread 0 as false and any other value as true
     *
     * @param string $content Content to unpack.
     *
     * @return bool Unpacked value.
     */
    protected function unpackBoolean(string $content): bool
    {
        $c = ord($content[0]);
        return $c != 0;
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
            return implode('-', [
                substr($value[1], 0, 8),
                substr($value[1], 8, 4),
                substr($value[1], 12, 4),
                substr($value[1], 16, 4),
                substr($value[1], 20)
            ]);
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
     * @param array $value        Value to pack.
     * @param ColumnType $subtype Values' Column type.
     *
     * @return string Binary form of the value.
     *
     * @throws \InvalidArgumentException
     */
    protected function packList(array $value, ColumnType $subtype): string
    {
        $retval = [$this->packInt(count($value))];

        foreach ($value as $item) {
            $itemPacked = $this->packValue($item, $subtype);
            $retval[] = $this->packLongString($itemPacked);
        }

        return implode($retval);
    }

    /**
     * Unpacks a COLUMNTYPE_LIST value from its binary form.
     *
     * @param string $content     Content to unpack.
     * @param ColumnType $subtype Values' Column type.
     *
     * @return array Unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackList(string $content, ColumnType $subtype): array
    {
        $contentOffset = 0;
        $itemsCount = $this->popInt($content, $contentOffset);
        $retval = [];

        for (; $itemsCount; $itemsCount--) {
            $subcontent = $this->popLongString($content, $contentOffset);
            $retval[] = $this->unpackValue($subcontent, $subtype);
        }

        return $retval;
    }

    /**
     * Packs a COLUMNTYPE_MAP value to its binary form.
     *
     * @param array $value         Value to pack.
     * @param ColumnType $subtype1 Keys' column type.
     * @param ColumnType $subtype2 Values' column type.
     *
     * @return string Binary form of the value.
     *
     * @throws \InvalidArgumentException
     */
    protected function packMap(array $value, ColumnType $subtype1, ColumnType $subtype2): string
    {
        $retval = [$this->packInt(count($value))];

        foreach ($value as $key => $item) {
            $keyPacked = $this->packValue($key, $subtype1);
            $itemPacked = $this->packValue($item, $subtype2);
            $retval[] = $this->packLongString($keyPacked);
            $retval[] = $this->packLongString($itemPacked);
        }

        return implode($retval);
    }

    /**
     * Unpacks a COLUMNTYPE_MAP value from its binary form.
     *
     * @param string $content      Content to unpack.
     * @param ColumnType $subtype1 Keys' column type.
     * @param ColumnType $subtype2 Values' column type.
     *
     * @return array Unpacked value.
     *
     * @throws CassandraException
     */
    protected function unpackMap(
        string $content,
        ColumnType $subtype1,
        ColumnType $subtype2
    ): array {
        $contentOffset = 0;
        $itemsCount = $this->popInt($content, $contentOffset);
        $retval = [];

        for (; $itemsCount; $itemsCount--) {
            $subKeyRaw = $this->popLongString($content, $contentOffset);
            $subValueRaw = $this->popLongString($content, $contentOffset);

            $subKey = $this->unpackValue($subKeyRaw, $subtype1);
            $subValue = $this->unpackValue($subValueRaw, $subtype2);
            $retval[$subKey] = $subValue;
        }

        return $retval;
    }

    /**
     * Packs a COLUMNTYPE_UDT value to its binary form.
     *
     * @param array $value    Value to pack
     * @param array $subTypes List of pairs of the field and type of the UDT.
     *
     * @return string
     *
     * @throws CassandraException
     */
    protected function packUDT(array $value, array $subTypes): string
    {
        $retval = [];

        foreach ($subTypes as $field => $type) {
            if (!isset($value[$field])) {
                throw new QueryException("UDT value missing field $field");
            }

            $retval[] = $this->packValue($value[$field], $type);
        }

        return implode($retval);
    }

    /**
     * Unpacks a COLUMNTYPE_UDT from its binary form
     *
     * @param string $content
     * @param array $fields
     *
     * @return array
     *
     * @throws CassandraException
     */
    protected function unpackUDT(string $content, array $fields): array
    {
        $contentOffset = 0;
        $retval = [];

        foreach ($fields as $name => $type) {
            $valueRaw = $this->popLongString($content, $contentOffset);
            $retval[$name] = $this->unpackValue($valueRaw, $type);
        }

        return $retval;
    }

    /**
     * Packs a COLUMNTYPE_TUPLE value to its binary form.
     *
     * @param array $value     The value to pack
     * @param array $subTypes  List of types for each item within the Tuple
     *
     * @return string
     *
     * @throws CassandraException
     */
    protected function packTuple(array $value, array $subTypes): string
    {
        $retval = [];
        $expected = count($subTypes);
        $actual = count($value);

        if ($expected !== $actual) {
            throw new QueryException("Tuple expects $expected fields, got $actual fields");
        }

        foreach ($subTypes as $i => $type) {
            $packedValue = $this->packValue($value[$i], $type);
            $retval[] = $this->packLongString($packedValue);
        }

        return implode($retval);
    }

    /**
     * Unpacks a COLUMNTYPE_TUPLE from its binary form
     *q
     * @param string $content
     * @param array $subTypes List of types for each item within the Tuple
     *
     * @return array
     *
     * @throws CassandraException
     */
    protected function unpackTuple(string $content, array $subTypes): array
    {
        $contentOffset = 0;
        $retval = [];

        foreach ($subTypes as $type) {
            $valueRaw = $this->popLongString($content, $contentOffset);
            $retval[] = $this->unpackValue($valueRaw, $type);
        }

        return $retval;
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
     * Pops a [bytes] value from the body, starting from the offset, and
     * advancing it in the process.
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
     * Pops a [long string] value from the body, starting from the offset, and
     * advancing it in the process.
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
     * Pops a [int] value from the body, starting from the offset, and
     * advancing it in the process.
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
     * Pops a [short] value from the body, starting from the offset, and
     * advancing it in the process.
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
     * @param Opcode $opcode Frame's opcode.
     * @param string $body   Frame's body.
     * @param int $stream    Frame's stream id.
     *
     * @return string Frame's content.
     *
     * @throws CassandraException
     */
    protected function packFrame(Opcode $opcode, string $body, int $stream): string
    {
        $version = (0 << 0x07) | self::PROTOCOL_VERSION;
        $flags = 0;

        // STARTUP and OPTION Messages cannot be compressed
        $invalidOpcodes = [Opcode::Startup, Opcode::Options];
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
            $opcode->value,
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
        $retval = [pack('n', count($dataArr))];

        foreach ($dataArr as $key => $value) {
            $retval[] = $this->packString($key);
            $retval[] = $this->packString($value);
        }

        return implode($retval);
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
}
