<?php 

namespace CassandraNative\Cluster;

use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Compression\CompressorInterface;
use CassandraNative\SSL\SSLOptions;

class ClusterOptions
{
    protected int $consistency;

    /**
     * @var string[]
     */
    protected array $hosts;

    protected ?AuthProviderInterface $authProvider;

    protected float $connectTimeout;

    protected float $requestTimeout;

    protected int $attempts;

    protected ?SSLOptions $ssl; 

    protected int $port;

    protected bool $persistent;

    /**
     * @var CompressorInterface[]
     */
    protected array $compressors;

    /**
     * @var string
     */
    protected string $compressionType;

    /**
     * @param int $consistency
     * @param string[] $hosts
     * @param ?AuthProviderInterface $authProvider
     * @param float $connectTimeout
     * @param float $requestTimeout
     * @param int $attempts
     * @param ?SSLOptions $ssl
     * @param int $port
     * @param bool $persistent
     * @param CompressorInterface[] $compressors
     * @param string $compressionType
     */
    public function __construct(
        int $consistency,
        array $hosts,
        ?AuthProviderInterface $authProvider,
        float $connectTimeout,
        float $requestTimeout,
        int $attempts,
        ?SSLOptions $ssl, 
        int $port,
        bool $persistent,
        array $compressors,
        string $compressionType
    ) {
        $this->consistency = $consistency;
        $this->hosts = $hosts;
        $this->authProvider = $authProvider;
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
        $this->attempts = $attempts;
        $this->ssl = $ssl;
        $this->port = $port;
        $this->persistent = $persistent;
        $this->compressors = $compressors;
        $this->compressionType = $compressionType;
    }

    /**
     * @return int
     */
    public function getDefaultConsistency(): int
    {
        return $this->consistency;
    }

    /**
     * @return string[]
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * @return ?AuthProviderInterface
     */
    public function getAuthProvider(): ?AuthProviderInterface
    {
        return $this->authProvider;
    }

    /**
     * @return float
     */
    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * @return float
     */
    public function getRequestTimeout(): float
    {
        return $this->requestTimeout;
    }

    /**
     * @return int
     */
    public function getMaxConnectionAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * @return ?SSLOptions
     */
    public function getSSL(): ?SSLOptions
    {
        return $this->ssl;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return bool
     */
    public function getPersistentSessions(): bool
    {
        return $this->persistent;
    }

    /**
     * @return CompressorInterface[]
     */
    public function getCompressors(): array
    {
        return $this->compressors;
    }

    /**
     * @return string
     */
    public function getCompressionType(): string
    {
        return $this->compressionType;
    }
}
