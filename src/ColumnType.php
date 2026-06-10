<?php

namespace CassandraNative;

enum ColumnType : int
{
    case Custom    = 0x0000;
    case Ascii     = 0x0001;
    case Bigint    = 0x0002;
    case Blob      = 0x0003;
    case Boolean   = 0x0004;
    case Counter   = 0x0005;
    case Decimal   = 0x0006;
    case Double    = 0x0007;
    case Float     = 0x0008;
    case Int       = 0x0009;
    case Text      = 0x000A;
    case Timestamp = 0x000B;
    case Uuid      = 0x000C;
    case Varchar   = 0x000D;
    case Varint    = 0x000E;
    case Timeuuid  = 0x000F;
    case Inet      = 0x0010;
    case List      = 0x0020;
    case Map       = 0x0021;
    case Set       = 0x0022;
}
