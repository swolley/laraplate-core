<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum FieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case SWITCH = 'switch';
    case SELECT = 'select';
    case RADIO = 'radio';
    case CHECKBOX = 'checkbox';
    case DATETIME = 'datetime';
    case NUMBER = 'number';
    case OBJECT = 'json';
    case ARRAY = 'array';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case URL = 'url';
    case EDITOR = 'editor';

    public function getRule(): string
    {
        return match ($this) {
            self::TEXT, self::TEXTAREA => 'string',
            self::SWITCH => 'boolean',
            self::CHECKBOX => 'array',
            // self::RADIO => 'string',
            // self::SELECT => 'string',
            self::DATETIME => 'date',
            self::NUMBER => 'number',
            self::OBJECT, self::EDITOR => 'json',
            self::ARRAY => 'array',
            self::EMAIL => 'email',
            self::PHONE => 'string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            self::URL => 'url',
            default => '',
        };
    }

    public function isTextual(): bool
    {
        return in_array($this, [self::TEXT, self::TEXTAREA, self::EDITOR], true);
    }
}
