<?php

namespace CassandraNative;

enum ColumnType : int
{
    case CUSTOM    = 0x0000;
    case ASCII     = 0x0001;
    case BIGINT    = 0x0002;
    case BLOB      = 0x0003;
    case BOOLEAN   = 0x0004;
    case COUNTER   = 0x0005;
    case DECIMAL   = 0x0006;
    case DOUBLE    = 0x0007;
    case FLOAT     = 0x0008;
    case INT       = 0x0009;
    case TEXT      = 0x000A;
    case TIMESTAMP = 0x000B;
    case UUID      = 0x000C;
    case VARCHAR   = 0x000D;
    case VARINT    = 0x000E;
    case TIMEUUID  = 0x000F;
    case INET      = 0x0010;
    case LIST      = 0x0020;
    case MAP       = 0x0021;
    case SET       = 0x0022;
}
