<?php

namespace Framework\Content;

use DateTime;

class Content
{
    public function __construct(
        public readonly int $id,
        public string $title,
        public string $slug,
        public string $content,
        public ContentType $type,
        public ContentStatus $status,
        public int $authorId,
        public ?int $parentId,
        public array $customFields,
        public DateTime $createdAt,
        public DateTime $updatedAt,
        public ?DateTime $publishedAt = null,
        public string $locale = 'en',
        public ?string $translationGroup = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'author_id' => $this->authorId,
            'parent_id' => $this->parentId,
            'custom_fields' => $this->customFields,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'locale' => $this->locale,
            'translation_group' => $this->translationGroup,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
