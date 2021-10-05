<?php declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpFoundation\Response;

/**
 * A wrapper for holding data to be used for a application/problem+json response
 */
class ApiProblem
{
    const TYPE_VALIDATION_ERROR = 'validation_error';
    const TYPE_INVALID_REQUEST_BODY_FORMAT = 'invalid_body_format';

    private static $titles = [
        self::TYPE_VALIDATION_ERROR => 'There was a validation error',
        self::TYPE_INVALID_REQUEST_BODY_FORMAT => 'Invalid JSON format sent',
    ];

    private $statusCode;
    private $type;
    private $title;
    private $extraData = [];

    public function __construct(int $statusCode, ?string $type = null)
    {
        $this->statusCode = $statusCode;

        if ($type === null) {
            $type = 'about:blank';
            $title = Response::$statusTexts[$statusCode] ?? 'Unknown status code :(';
        } else {
            if (!isset(self::$titles[$type])) {
                throw new \InvalidArgumentException('No title for type '. $type);
            }
            $title = self::$titles[$type];
        }

        $this->type = $type;
        $this->title = $title;
    }

    public function toArray(): array
    {
        return array_merge(
            $this->extraData, [
                'status' => $this->statusCode,
                'type' => $this->type,
                'title' => $this->title,
            ]
        );
    }

    public function set(string $name, $value): void
    {
        $this->extraData[$name] = $value;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
