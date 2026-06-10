<?php

namespace CassandraNative;

enum Opcode : int
{
    case Error         = 0x00;
    case Startup       = 0x01;
    case Ready         = 0x02;
    case Authenticate  = 0x03;
    case Options       = 0x05;
    case Supported     = 0x06;
    case Query         = 0x07;
    case Result        = 0x08;
    case Prepare       = 0x09;
    case Execute       = 0x0A;
    case Register      = 0x0B;
    case Event         = 0x0C;
    case Batch         = 0x0D;
    case AuthChallenge = 0x0E;
    case AuthResponse  = 0x0F;
    case AuthSuccess   = 0x10;
}
