<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

enum ContentMimeTypesEnum: string
{
    case TEXT_HTML = 'text/html';
    case APPLICATION_JSON = 'application/json';
    case TEXT_PLAIN = 'text/plain';
    // case IMAGE_PNG = 'image/png';
    // case IMAGE_JPEG = 'image/jpeg';
    // case IMAGE_GIF = 'image/gif';
    // case IMAGE_SVG_XML = 'image/svg+xml';
    // case AUDIO_MPEG = 'audio/mpeg';
    // case VIDEO_MP4 = 'video/mp4';

    public function getContentEventClass(): string
    {
        return match ($this) {
            self::TEXT_HTML => \Zolinga\System\Events\Content\HtmlContentEvent::class,
            self::APPLICATION_JSON => \Zolinga\System\Events\Content\JsonContentEvent::class,
            self::TEXT_PLAIN => \Zolinga\System\Events\Content\TextContentEvent::class,
            // self::IMAGE_PNG, self::IMAGE_JPEG, self::IMAGE_GIF, self::IMAGE_SVG_XML => \Zolinga\System\Events\Content\ImageContentEvent::class,
            // self::AUDIO_MPEG => \Zolinga\System\Events\Content\AudioContentEvent::class,
            // self::VIDEO_MP4 => \Zolinga\System\Events\Content\VideoContentEvent::class,
            default => throw new \InvalidArgumentException("No associated ContentEvent class for MIME type: {$this->value}"),
        };
    }
}