<?php

namespace CassandraNative;

enum Consistency : int
{
    case ANY          = 0x0000;
    case ONE          = 0x0001;
    case TWO          = 0x0002;
    case THREE        = 0x0003;
    case QUORUM       = 0x0004;
    case ALL          = 0x0005;
    case LOCAL_QUORUM = 0x0006;
    case EACH_QUORUM  = 0x0007;
    case LOCAL_ONE    = 0x000A;
}
