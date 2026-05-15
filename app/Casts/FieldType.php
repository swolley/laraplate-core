<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Switch = 'switch';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Datetime = 'datetime';
    case Number = 'number';
    case Object = 'json';
    case Array = 'array';
    case Email = 'email';
    case Phone = 'phone';
    case Url = 'url';
    case Editor = 'editor';

    public function getRule(): string
    {
        return match ($this) {
            self::Text, self::Textarea => 'string',
            self::Switch => 'boolean',
            self::Checkbox => 'array',
            // self::Radio => 'string',
            // self::Select => 'string',
            self::Datetime => 'date',
            self::Number => 'number',
            self::Object, self::Editor => 'json',
            self::Array => 'array',
            self::Email => 'email',
            self::Phone => 'string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            self::Url => 'url',
            default => '',
        };
    }

    public function isTextual(): bool
    {
        return in_array($this, [self::Text, self::Textarea, self::Editor], true);
    }
}
