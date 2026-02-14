<?php

namespace Framework\Content;

enum ContentStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case SCHEDULED = 'scheduled';
    case TRASH = 'trash';
}
