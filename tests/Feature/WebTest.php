<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebTest extends TestCase
{
    public function test_home_redirects_to_interactive_documentation(): void
    {
        $this->get('/')->assertRedirect('/docs/index.html');
    }

    public function test_api_index_lists_entry_points(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonStructure(['name', 'docs', 'openapi', 'api', 'health']);
    }

    public function test_documentation_files_are_published(): void
    {
        $this->assertFileExists(public_path('docs/index.html'));
        $this->assertStringContainsString('openapi: 3.0.0', file_get_contents(public_path('docs/openapi.yaml')));
    }

    public function test_api_index_links_ignore_the_host_header(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $this->getJson('/api', ['Host' => 'evil.example'])
            ->assertOk()
            ->assertJsonPath('docs', 'http://localhost:8080/docs/')
            ->assertJsonPath('api', 'http://localhost:8080/api/v1');
    }
}
