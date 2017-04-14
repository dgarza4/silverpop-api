<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApiTest extends TestCase
{
    /**
     * Get list of databases.
     *
     * @return void
     */
    public function testGetDatabases()
    {
        $response = $this->json('GET', '/api/databases');

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => []
            ]);
    }

    /**
     * Get single database.
     *
     * @return void
     */
    public function testGetSingleDatabase()
    {
        $databaseId = 1847712;

        $response = $this->json('GET', '/api/databases/' . $databaseId);

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => [
                    'ID' => $databaseId
                ]
            ]);
    }

    /**
     * Get size of single database.
     *
     * @return void
     */
    public function testGetSizeSingleDatabase()
    {
        $databaseId = 1847712;

        $response = $this->json('GET', '/api/databases/' . $databaseId . '/count');

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => []
            ]);
    }

    /**
     * Get single contact in database.
     *
     * @return void
     */
    public function testGetSingleContactInDatabase()
    {
        $databaseId = 1847712;
        $contactEmail = 't@t.com';

        $response = $this->json('GET', '/api/databases/' . $databaseId . '/contacts/' . $contactEmail);

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => [
                    'contactLists' => []
                ]
            ]);
    }

    /**
     * Export single database.
     *
     * @return void
     */
    public function testExportDatabase()
    {
        $databaseId = 3417532;

        $response = $this->json('GET', '/api/databases/' . $databaseId . '/export');

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => [
                    'export' => [
                        'jobId' => [],
                        'filePath' => []
                    ]
                ]
            ]);
    }

    /**
     * Create contact.
     *
     * @return void
     */
    public function testCreateContact()
    {
        $databaseId = 1847712;
        $contact = [
            'fields' => [
                'email' => 'test@test.com',
                'First Name' => 'Test',
                'last name' => 'Last'
            ],
            'contactLists' => [2164069, 2164076],
            'upsert' => true
        ];

        $response = $this->json('POST', '/api/databases/' . $databaseId . '/contact', $contact);

        $response
            ->assertStatus(200)
            ->assertJson([
                'results' => true
            ]);
    }
}
