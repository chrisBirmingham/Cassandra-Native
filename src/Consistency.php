<?php

namespace CassandraNative;

enum Consistency : int
{
    case Any         = 0x0000;
    case One         = 0x0001;
    case Two         = 0x0002;
    case Three       = 0x0003;
    case Quorum      = 0x0004;
    case All         = 0x0005;
    case LocalQuorum = 0x0006;
    case EachQuorum  = 0x0007;
    case LocalOne    = 0x000A;
}
