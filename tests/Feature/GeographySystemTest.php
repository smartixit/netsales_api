<?php

namespace Tests\Feature;

use App\Models\Directorate;
use App\Models\Governorate;
use App\Models\User;
use App\Models\UserSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GeographySystemTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected UserSession $session;

    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::firstOrFail();

        $auth = $this->createAuthenticatedSession($this->user);
        $this->session = $auth['session'];
        $this->headers = $auth['headers'];
    }

    public function test_can_manage_governorate_crud(): void
    {
        $payload = [
            'governorate_name' => 'محافظة تجريبية خاصة',
        ];

        // 1. Create
        $response = $this->postJson('/api/v1/governorates', $payload, $this->headers);
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.governorate_name', 'محافظة تجريبية خاصة');

        $govId = $response->json('data.governorate_id');

        // 2. List
        $listResponse = $this->getJson('/api/v1/governorates?search=تجريبية', $this->headers);
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($listResponse->json('data'));

        // 3. Show
        $showResponse = $this->getJson("/api/v1/governorates/{$govId}", $this->headers);
        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.governorate_id', $govId);

        // 4. Update
        $updateResponse = $this->putJson("/api/v1/governorates/{$govId}", ['governorate_name' => 'محافظة تجريبية محدثة'], $this->headers);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.governorate_name', 'محافظة تجريبية محدثة');

        // 5. Counts endpoint
        $countsResponse = $this->getJson('/api/v1/governorates/counts', $this->headers);
        $countsResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_manage_directorate_crud_and_relations(): void
    {
        $gov = Governorate::create([
            'governorate_name' => 'محافظة لاختبار المديريات',
        ]);

        $payload = [
            'governorate_id' => $gov->governorate_id,
            'directorate_name' => 'مديرية تجريبية 1',
        ];

        // 1. Create Directorate
        $response = $this->postJson('/api/v1/directorates', $payload, $this->headers);
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.directorate_name', 'مديرية تجريبية 1');

        $dirId = $response->json('data.directorate_id');

        // 2. List with filters
        $listResponse = $this->getJson("/api/v1/directorates?governorate_id={$gov->governorate_id}", $this->headers);
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($listResponse->json('data'));

        // 3. By Governorate
        $byGovResponse = $this->getJson("/api/v1/directorates/governorate/{$gov->governorate_id}", $this->headers);
        $byGovResponse->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($byGovResponse->json('data'));

        // 4. Update Directorate
        $updateResponse = $this->putJson("/api/v1/directorates/{$dirId}", ['directorate_name' => 'مديرية محدثة تماماً'], $this->headers);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.directorate_name', 'مديرية محدثة تماماً');

        // 5. Integrity check: cannot delete governorate if it has active directorates
        $delGovResponse = $this->deleteJson("/api/v1/governorates/{$gov->governorate_id}", [], $this->headers);
        $delGovResponse->assertStatus(422)
            ->assertJsonPath('success', false);

        // 6. Delete directorate first
        $delDirResponse = $this->deleteJson("/api/v1/directorates/{$dirId}", [], $this->headers);
        $delDirResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // 7. Now governorate can be deleted
        $delGovSuccess = $this->deleteJson("/api/v1/governorates/{$gov->governorate_id}", [], $this->headers);
        $delGovSuccess->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_bulk_operations_on_geography(): void
    {
        $g1 = Governorate::create(['governorate_name' => 'محافظة جماعية 1']);
        $g2 = Governorate::create(['governorate_name' => 'محافظة جماعية 2']);
        $ids = [$g1->governorate_id, $g2->governorate_id];

        // Batch delete
        $delResp = $this->postJson('/api/v1/governorates/batch-delete', ['ids' => $ids], $this->headers);
        $delResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2);

        // Batch restore
        $resResp = $this->postJson('/api/v1/governorates/batch-restore', ['ids' => $ids], $this->headers);
        $resResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.restored_count', 2);

        // Batch force delete
        $forceResp = $this->postJson('/api/v1/governorates/batch-force-delete', ['ids' => $ids], $this->headers);
        $forceResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.force_deleted_count', 2);

        $this->assertEmpty(Governorate::withTrashed()->whereIn('governorate_id', $ids)->get());
    }
}
