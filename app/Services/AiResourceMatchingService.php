<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiResourceMatchingService
{
    /**
     * Build the prompt for the AI model.
     *
     * Sends the lesson summary alongside all available resource tags
     * and asks the model to rank them by relevance.
     *
     * @param  array<int, string>  $availableTags
     */
    public function buildPrompt(string $lessonSummary, array $availableTags): string
    {
        $tagsJson = json_encode(array_values($availableTags), JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a driving instructor assistant for a UK driving school. A student has just completed a driving lesson and their instructor wrote a summary of what was covered.

Your task is to match the lesson content against our video resource library by ranking which tags are most relevant.

## Lesson Summary
"{$lessonSummary}"

## Available Resource Tags
{$tagsJson}

## Instructions
1. Analyse the lesson summary to understand what driving topics were covered.
2. From the available tags above, select the tags that are most relevant to the lesson content.
3. Rank them from most relevant to least relevant.
4. Return ONLY a JSON array of the top 10 most relevant tags (or fewer if less than 10 are relevant).
5. Do NOT invent new tags — only use tags from the list provided.
6. If no tags are relevant, return an empty array [].

## Response Format
Respond with ONLY a valid JSON array, no explanation or markdown. Example:
["roundabouts", "right turn", "signalling", "mirror checks"]
PROMPT;
    }

    /**
     * Call OpenRouter AI to rank tags by relevance to the lesson summary.
     *
     * @param  array<int, string>  $availableTags
     * @return array<int, string> Ranked tags (most relevant first)
     */
    public function rankTags(string $lessonSummary, array $availableTags): array
    {
        if (empty($availableTags) || empty(trim($lessonSummary))) {
            return [];
        }

        $apiKey = config('services.openrouter.api_key');

        if (! $apiKey) {
            Log::warning('AI resource matching skipped: OPENROUTER_API_KEY not configured');

            return [];
        }

        $prompt = $this->buildPrompt($lessonSummary, $availableTags);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('services.openrouter.model', 'amazon/nova-lite-v1'),
                'max_tokens' => config('services.openrouter.max_tokens', 1024),
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('AI resource matching: OpenRouter API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $responseText = $response->json('choices.0.message.content', '[]');

            Log::info('AI resource matching completed', [
                'summary_length' => strlen($lessonSummary),
                'available_tags_count' => count($availableTags),
                'model' => config('services.openrouter.model'),
            ]);

            return $this->parseResponse($responseText, $availableTags);
        } catch (\Throwable $e) {
            Log::error('AI resource matching failed', [
                'error' => $e->getMessage(),
                'summary_length' => strlen($lessonSummary),
            ]);

            return [];
        }
    }

    /**
     * Parse the AI response and validate tags against the available list.
     *
     * @param  array<int, string>  $availableTags
     * @return array<int, string>
     */
    protected function parseResponse(string $responseText, array $availableTags): array
    {
        // Strip any markdown code fences the model might wrap around the JSON
        $cleaned = trim($responseText);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            Log::warning('AI resource matching returned invalid JSON', [
                'response' => substr($responseText, 0, 500),
            ]);

            return [];
        }

        // Only keep tags that actually exist in our database
        $validTags = array_map('strtolower', $availableTags);

        return array_values(array_filter(
            $decoded,
            fn (mixed $tag) => is_string($tag) && in_array(strtolower($tag), $validTags, true)
        ));
    }
}
