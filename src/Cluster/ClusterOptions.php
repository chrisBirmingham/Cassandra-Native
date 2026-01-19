<?php 

namespace CassandraNative\Cluster;

use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Compression\CompressorInterface;
use CassandraNative\Consistency;
use CassandraNative\SSL\SSLOptions;

readonly class ClusterOptions
{
    public Consistency $consistency;

    /**
     * @var string[]
     */
    public array $hosts;

    public ?AuthProviderInterface $authProvider;

    public float $connectTimeout;

    public float $requestTimeout;

    public int $attempts;

    public ?SSLOptions $ssl;

    public int $port;

    public bool $persistent;

    public ?CompressorInterface $compressor;

    /**
     * @param Consistency $consistency
     * @param string[] $hosts
     * @param ?AuthProviderInterface $authProvider
     * @param float $connectTimeout
     * @param float $requestTimeout
     * @param int $attempts
     * @param ?SSLOptions $ssl
     * @param int $port
     * @param bool $persistent
     * @param ?CompressorInterface $compressor
     */
    public function __construct(
        Consistency $consistency,
        array $hosts,
        ?AuthProviderInterface $authProvider,
        float $connectTimeout,
        float $requestTimeout,
        int $attempts,
        ?SSLOptions $ssl, 
        int $port,
        bool $persistent,
        ?CompressorInterface $compressor
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
        $this->compressor = $compressor;
    }
}
