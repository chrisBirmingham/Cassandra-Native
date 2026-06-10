<?php

namespace CassandraNative;

enum ResultKind : int
{
    case Void         = 0x001;
    case Rows         = 0x002;
    case SetKeyspace  = 0x003;
    case Prepared     = 0x004;
    case SchemaChange = 0x005;
}
