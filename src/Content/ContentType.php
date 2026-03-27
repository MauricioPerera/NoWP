<?php

declare(strict_types=1);

namespace ChimeraNoWP\Content;

enum ContentType: string
{
    case POST = 'post';
    case PAGE = 'page';
    case CUSTOM = 'custom';
}
