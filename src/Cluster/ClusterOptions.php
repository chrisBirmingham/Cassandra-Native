<?php 

namespace CassandraNative\Cluster;

use CassandraNative\Auth\AuthProviderInterface;
use CassandraNative\Compression\CompressorInterface;
use CassandraNative\Consistency;
use CassandraNative\SSL\SSLOptions;

readonly class ClusterOptions
{
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
        public Consistency $consistency,
        public array $hosts,
        public ?AuthProviderInterface $authProvider,
        public float $connectTimeout,
        public float $requestTimeout,
        public int $attempts,
        public ?SSLOptions $ssl,
        public int $port,
        public bool $persistent,
        public ?CompressorInterface $compressor
    ) {}
}
