<?php

declare(strict_types=1);

namespace OpenAI\Responses\Chat;

use OpenAI\Contracts\ResponseContract;
use OpenAI\Contracts\ResponseHasMetaInformationContract;
use OpenAI\Responses\Concerns\ArrayAccessible;
use OpenAI\Responses\Concerns\HasMetaInformation;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Testing\Responses\Concerns\Fakeable;

/**
 * @implements ResponseContract<array{id: string, object: string, created: int, model: string, system_fingerprint?:
 *             string, choices: array<int, array{index: int, message: array{role: string, content: string|null,
 *             function_call?: array{name: string, arguments: string}, tool_calls?: array<int, array{id: string, type:
 *             string, function: array{name: string, arguments: string}}>}, finish_reason: string|null}>, usage:
 *             array{prompt_tokens: int, completion_tokens: int|null, total_tokens: int}}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, object: string, created: int, model: string, system_fingerprint?: string,
     *      choices: array<int, array{index: int, message: array{role: string, content: string|null, function_call?:
     *      array{name: string, arguments: string}, tool_calls?: array<int, array{id: string, type: string, function:
     *      array{name: string, arguments: string}}>}, finish_reason: string|null}>, usage: array{prompt_tokens: int,
     *      completion_tokens: int|null, total_tokens: int}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param array<int, CreateResponseChoice> $choices
     */
    private function __construct(
        public readonly string              $id,
        public readonly string              $object,
        public readonly int                 $created,
        public readonly string              $model,
        public readonly ?string             $systemFingerprint,
        public readonly array               $choices,
        public readonly CreateResponseUsage $usage,
        private readonly MetaInformation    $meta,
    )
    {
    }

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param array{id: string, object: string, created: int, model: string, system_fingerprint?: string, choices:
     *                          array<int, array{index: int, message: array{role: string, content: ?string,
     *                          function_call: ?array{name: string, arguments: string}, tool_calls: ?array<int,
     *                          array{id: string, type: string, function: array{name: string, arguments: string}}>},
     *                          finish_reason: string|null}>, usage: array{prompt_tokens: int, completion_tokens:
     *                          int|null, total_tokens: int, prompt_tokens_details?:array{cached_tokens:int},
     *                          completion_tokens_details?:array{audio_tokens?:int, reasoning_tokens:int,
     *                          accepted_prediction_tokens:int, rejected_prediction_tokens:int}}} $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $choices = [];

        // Process choices with individual error handling
        if (isset($attributes['choices']) && is_array($attributes['choices'])) {
            foreach ($attributes['choices'] as $result) {
                try {
                    $choices[] = CreateResponseChoice::from($result);
                } catch (\Throwable $e) {
                    // Log the error with specific details
                    error_log(sprintf(
                        'Failed to process choice: %s. Error: %s',
                        json_encode($result),
                        $e->getMessage()
                    ));
                    // Continue processing other choices instead of breaking
                }
            }
        }

        // Handle usage data safely
        try {
            $usage = isset($attributes['usage']) ?
                CreateResponseUsage::from($attributes['usage']) :
                CreateResponseUsage::from([
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'total_tokens' => 0,
                ]);
        } catch (\Throwable $e) {
            error_log(sprintf('Failed to process usage data: %s', $e->getMessage()));
            // Create minimal valid usage data structure
            $usage = CreateResponseUsage::from([
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ]);
        }

        $id = $attributes['id'] ?? null;

        return new self(
            $id,
            $attributes['object'],
            $attributes['created'],
            $attributes['model'],
            $attributes['system_fingerprint'] ?? null,
            $choices,
            $usage,
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id ?? null,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
            'system_fingerprint' => $this->systemFingerprint,
            'choices' => array_map(
                static fn(CreateResponseChoice $result): array => $result->toArray(),
                $this->choices,
            ),
            'usage' => $this->usage->toArray(),
        ], fn(mixed $value): bool => !is_null($value));
    }
}
