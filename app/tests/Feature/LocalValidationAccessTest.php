<?php

namespace Tests\Feature;

use Tests\TestCase;

final class LocalValidationAccessTest extends TestCase
{
    public function test_the_indicator_allows_local_validation_without_a_session(): void
    {
        app()->detectEnvironment(fn (): string => 'local');
        config(['validation.bypass_login' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'])
            ->get('http://localhost/')
            ->assertRedirect('/charts');
    }

    public function test_the_indicator_does_not_open_the_public_application(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config(['validation.bypass_login' => true]);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'])
            ->get('http://localhost/')
            ->assertRedirect('/login');
    }

    public function test_remote_requests_cannot_spoof_a_local_address(): void
    {
        app()->detectEnvironment(fn (): string => 'local');
        config(['validation.bypass_login' => true]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_HOST' => 'localhost',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1',
        ])->get('http://localhost/')->assertRedirect('/login');
    }

    public function test_a_public_host_is_not_opened_through_a_local_proxy(): void
    {
        app()->detectEnvironment(fn (): string => 'local');
        config(['validation.bypass_login' => true]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'cartanataldhara.synology.me',
        ])->get('/')->assertRedirect('/login');
    }
}
