<?php

return [
    'image_base_url' => env('MOCK_TEST_IMAGE_BASE_URL', '/storage/mock-test-images'),
    'pass_mark' => 43,
    'questions_per_test' => (int) env('MOCK_TEST_QUESTIONS', 50),
];
