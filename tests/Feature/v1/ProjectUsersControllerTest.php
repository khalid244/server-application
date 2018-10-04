<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ProjectUsersControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function setUp()
    {
        parent::setUp();

        Artisan::call('db:seed');
    }

    public function test_BulkCreate_ExpectPass()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAdminToken()
        ];

        $data = [
            'relations' => [
                [
                    'project_id'  => 1,
                    'user_id'     => 1
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/projects-users/bulk-create', $data, $headers);

        $response->assertStatus(200);
    }

    public function test_BulkDestroy_ExpectPass()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAdminToken()
        ];

        $data = [
            'relations' => [
                [
                    'project_id'  => 1,
                    'user_id'     => 1
                ]
            ]
        ];

        $this->postJson('/api/v1/projects-users/bulk-create', $data, $headers);
        $response = $this->postJson('/api/v1/projects-users/bulk-destroy', $data, $headers);

        $response->assertStatus(200);
    }

    public function test_Create_ExpectPass()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAdminToken()
        ];

        $data = [
                'project_id'  => 1,
                'user_id'     => 1
        ];

        $response = $this->postJson('/api/v1/projects-users/create', $data, $headers);

        $response->assertStatus(200);
    }

    public function test_Destroy_ExpectPass()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAdminToken()
        ];

        $data = [
            'project_id'  => 1,
            'user_id'     => 1
        ];

        $this->postJson('/api/v1/projects-users/create', $data, $headers);
        $response = $this->postJson('/api/v1/projects-users/destroy', $data, $headers);

        $response->assertStatus(200);
    }

    public function test_List_ExpectPass()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAdminToken()
        ];

        // Add user
        $createUserData = [
            "id"            => 1,
            "project_id"    => 1,
            "role_id"       => 1,
        ];
        $ths->postJson('/api/v1/project-users/create', $createUserData, $headers);

        $response = $this->getJson('/api/v1/projects-users/list', $headers);

        $expectedJson = [
            ''
        ];

        $response
            ->assertStatus(200)
            ->assertJson($expectedJson)
            ;
    }
}
