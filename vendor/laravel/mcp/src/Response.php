<?php

declare(strict_types=1);

namespace Laravel\Mcp;

use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use JsonException;
use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Exceptions\NotImplementedException;
use Laravel\Mcp\Server\Content\Blob;
use Laravel\Mcp\Server\Content\Notification;
use Laravel\Mcp\Server\Content\Text;
use Laravel\Mcp\Server\Contracts\Content;

class Response
{
    use Conditionable;
    use Macroable;

    protected function __construct(
        protected Content $content,
        protected Role $role = Role::USER,
        protected bool $isError = false,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function notification(string $method, array $params = []): static
    {
        return new static(new Notification($method, $params));
    }

    public static function text(string $text): static
    {
        return new static(new Text($text));
    }

    /**
     * @internal
     *
     * @throws JsonException
     */
    public static function json(mixed $content): static
    {
        return static::text(json_encode(
            $content,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT,
        ));
    }

    public static function blob(string $content): static
    {
        return new static(new Blob($content));
    }

    public static function error(string $text): static
    {
        return new static(new Text($text), isError: true);
    }

    public function content(): Content
    {
        return $this->content;
    }

    /**
     * @throws NotImplementedException
     */
    public static function audio(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    /**
     * @throws NotImplementedException
     */
    public static function image(): Content
    {
        throw NotImplementedException::forMethod(static::class, __METHOD__);
    }

    public function asAssistant(): static
    {
        return new static($this->content, Role::ASSISTANT, $this->isError);
    }

    public function isNotification(): bool
    {
        return $this->content instanceof Notification;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
