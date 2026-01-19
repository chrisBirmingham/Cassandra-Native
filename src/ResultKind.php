<?php

namespace CassandraNative;

enum ResultKind : int
{
    case VOID          = 0x001;
    case ROWS          = 0x002;
    case SET_KEYSPACE  = 0x003;
    case PREPARED      = 0x004;
    case SCHEMA_CHANGE = 0x005;
}
