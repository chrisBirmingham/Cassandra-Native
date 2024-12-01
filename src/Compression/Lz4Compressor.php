<?php

namespace CassandraNative\Compression;

class Lz4Compressor implements CompressorInterface
{
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'lz4';
    }

    /**
     * @inheritDoc
     */
    public function compress(string $data): string|false
    {
        // The first four characters of an LZ4 compressed body are the original data's length in little endian format.
        // This format is invalid for Cassandra which requires the first four characters to be in big endian.
        // We have to replace the header with our own one
        $len = strlen($data);

        if (($data = lz4_compress($data)) === false) {
            return false;
        }

        return pack('N', $len) . substr($data, 4);
    }

    /**
     * @inheritDoc
     */
    public function uncompress(string $data): string|false
    {
        // Cassandra responds with the first four characters in big endian. Like with compression we have to get the
        // header and convert it into little endian for PHP LZ4 to process
        $header = unpack('N', substr($data, 0, 4));

        // Some responses from Cassandra are empty and PHP LZ4 doesn't like that
        if ($header[1] === 0) {
            return '';
        }

        $data = pack('V', $header[1]) . substr($data, 4);
        return lz4_uncompress($data);
    }
}
