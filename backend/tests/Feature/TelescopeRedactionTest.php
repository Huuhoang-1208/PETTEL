<?php

namespace Tests\Feature;

use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\RequestWatcher;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class TelescopeRedactionTest extends TestCase
{
    public function test_sensitive_request_data_headers_and_response_fields_are_hidden(): void
    {
        $this->app->register(TelescopeServiceProvider::class);

        $watcher = new class extends RequestWatcher
        {
            public function redactPayload(array $payload): array
            {
                return $this->payload($payload);
            }

            public function redactHeaders(array $headers): array
            {
                return $this->headers($headers);
            }

            public function redactJsonResponse(Response $response): array|string
            {
                return $this->response($response);
            }
        };

        $payload = $watcher->redactPayload([
            'password' => 'password-secret',
            'token' => 'request-token-secret',
            'access_token' => 'access-token-secret',
            'name' => 'Visible name',
        ]);

        $this->assertSame('********', $payload['password']);
        $this->assertSame('********', $payload['token']);
        $this->assertSame('********', $payload['access_token']);
        $this->assertSame('Visible name', $payload['name']);

        $headers = $watcher->redactHeaders([
            'authorization' => ['Bearer header-token-secret'],
            'cookie' => ['session-cookie-secret'],
        ]);

        $this->assertSame('********', $headers['authorization']);
        $this->assertSame('********', $headers['cookie']);

        $response = response()->json([
            'token' => 'response-token-secret',
            'access_token' => 'response-access-token-secret',
        ]);

        $redactedResponse = $watcher->redactJsonResponse($response);

        $this->assertSame('********', $redactedResponse['token']);
        $this->assertSame('********', $redactedResponse['access_token']);

        $this->assertContains('password', Telescope::$hiddenRequestParameters);
        $this->assertContains('authorization', Telescope::$hiddenRequestHeaders);
    }
}
