<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        // Tes yang memalsukan error OpenAI tidak menunggu jeda percobaan ulang; OpenAiRetryTest menguji jalurnya.
        config()->set('services.openai.retry_ms', []);
    }
}
