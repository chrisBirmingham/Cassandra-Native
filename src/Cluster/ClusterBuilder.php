<?php

namespace CassandraNative\Cluster;

use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Cassandra;
use CassandraNative\Compression\Lz4Compressor;
use CassandraNative\Compression\SnappyCompressor;
use CassandraNative\SSL\SSLOptions;

class ClusterBuilder
{
    protected int $consistency = Cassandra::CONSISTENCY_ONE;

    /**
     * @var string[]
     */
    protected array $hosts = ['localhost'];

    protected ?AuthProviderInterface $authProvider = null;

    protected float $connectTimeout = 30;

    protected float $requestTimeout = 30;

    protected int $attempts = 3;

    protected ?SSLOptions $ssl = null; 

    protected int $port = 9042;

    protected bool $persistent = false;

    protected bool $useCompression = false;

    protected string $compressionType = "lz4";

    /**
     * Sets the default consistency for all queries to the cluster. Default is CONSISTENCY_ONE
     *
     * @param int $consistency
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function withDefaultConsistency(int $consistency): static
    {
        if ($consistency < Cassandra::CONSISTENCY_ANY || $consistency > Cassandra::CONSISTENCY_LOCAL_ONE) {
            throw new \InvalidArgumentException('Invalid consistency provided. Must be between CONSISTENCY_ANY and CONSISTENCY_LOCAL_ONE');
        }

        $this->consistency = $consistency;
        return $this;
    }

    /**
     * Sets a list of initial hosts to connect too. The client will pick one at random to attempt a connection
     * Default is localhost
     *
     * @param string[] $hosts
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function withContactPoints(array $hosts): static
    {
        if (count($hosts) < 1) {
            throw new \InvalidArgumentException('Contact hosts cannot be empty');
        }

        $this->hosts = $hosts;
        return $this;
    }

    /**
     * Sets the max number of hosts the client will try to connect to before failing. Default is 3
     *
     * @param int $attempts
     * @return $this
     */
    public function withMaxConnectionAttempts(int $attempts): static
    {
        if ($attempts <= 0) {
            throw new \InvalidArgumentException('Max attempts cannot be less than 1');
        }

        $this->attempts = $attempts;
        return $this;
    }

    /**
     * Sets the port to connect too. Default is 9042
     *
     * @param int $port
     * @return $this
     */
    public function withPort(int $port): static
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Sets the plaintext credentials for authenticating the connection to the cluster
     * Default to no authentication
     *
     * @param AuthProviderInterface $authProvider
     * @return $this
     */
    public function withCredentials(AuthProviderInterface $authProvider): static
    {
        $this->authProvider = $authProvider;
        return $this;
    }

    /**
     * Sets timeout for connecting to the Cluster
     * Default 30 seconds
     *
     * @param float $timeout
     * @return $this
     */
    public function withConnectTimeout(float $timeout): static
    {
        $this->connectTimeout = $timeout;
        return $this;
    }

    /**
     * Sets the timeout for requests to the cluster
     * Default 30 seconds
     *
     * @param float $timeout
     * @return $this
     */
    public function withRequestTimeout(float $timeout): static
    {
        $this->requestTimeout = $timeout;
        return $this;
    }

    /**
     * Sets SSL connection settings built via the SSLBuilder object
     * Default is unencrypted
     *
     * @param SSLOptions $ssl
     * @return $this
     */
    public function withSSL(SSLOptions $ssl): static
    {
        $this->ssl = $ssl;
        return $this;
    }

    /**
     * Sets whether the connection to the cluster should be persistent or not.
     * Default is no persistent connections
     *
     * @param bool $enabled
     * @return $this
     */
    public function withPersistentSessions(bool $enabled): static
    {
        $this->persistent = $enabled;
        return $this;
    }

    /**
     * Sets whether the communication to the cluster should be compressed
     * If enabled, the driver will prefer LZ4 over snappy if both are available
     * Default is no compression
     *
     * @param bool $enabled
     * @return $this
     */
    public function withCompression(bool $enabled): static
    {
        $this->useCompression = $enabled;
        return $this;
    }

    /**
     * Set the compression type, essentially forces the client to use
     * a certain type of compression rather than finding out from the node
     *
     * @param string $type
     * @return $this
     */
    public function withCompressionType(string $type): static
    {
        $this->compressionType = type;
        return $this;
    }

    /**
     * Builds a cluster based on current settings
     *
     * @return Cassandra
     * @throws \Exception
     */
    public function build(): Cassandra
    {
        $compressors = [];
        if ($this->useCompression) {
            $extensions = get_loaded_extensions();
            if (in_array('lz4', $extensions)) {
                $compressors[] = new Lz4Compressor();
            }

            if (in_array('snappy', $extensions)) {
                $compressors[] = new SnappyCompressor();
            }

            if (empty($compressors)) {
                throw new \Exception('Compression enabled but lz4 and snappy extensions are not available');
            }
        }

        $options = new ClusterOptions(
            $this->consistency,
            $this->hosts,
            $this->authProvider,
            $this->connectTimeout,
            $this->requestTimeout,
            $this->attempts,
            $this->ssl,
            $this->port,
            $this->persistent,
            $compressors,
            $this->compressionType
        );

        return new Cassandra($options);
    }
}
