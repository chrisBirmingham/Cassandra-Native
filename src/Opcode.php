<?php

namespace CassandraNative;

enum Opcode : int
{
    case ERROR          = 0x00;
    case STARTUP        = 0x01;
    case READY          = 0x02;
    case AUTHENTICATE   = 0x03;
    case OPTIONS        = 0x05;
    case SUPPORTED      = 0x06;
    case QUERY          = 0x07;
    case RESULT         = 0x08;
    case PREPARE        = 0x09;
    case EXECUTE        = 0x0A;
    case REGISTER       = 0x0B;
    case EVENT          = 0x0C;
    case BATCH          = 0x0D;
    case AUTH_CHALLENGE = 0x0E;
    case AUTH_RESPONSE  = 0x0F;
    case AUTH_SUCCESS   = 0x10;
}
