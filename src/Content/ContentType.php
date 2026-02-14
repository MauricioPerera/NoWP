<?php

namespace Framework\Content;

enum ContentType: string
{
    case POST = 'post';
    case PAGE = 'page';
    case CUSTOM = 'custom';
}
