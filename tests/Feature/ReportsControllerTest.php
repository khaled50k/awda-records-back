<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Patient;
use App\Models\MedicalRecord;
use App\Models\RecordTransfer;
use App\Models\StaticData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ReportsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // Create users
        $this->user = User::factory()->create([
            'role_code' => 'employee',
            'is_active' => true
        ]);
        
        $this->admin = User::factory()->create([
            'role_code' => 'admin',
            'is_active' => true
        ]);

        // Create static data for problem types
        StaticData::create([
            'type' => 'problem_type',
            'code' => 'test_problem',
            'label_ar' => 'مشكلة تجريبية',
            'label_en' => 'Test Problem',
            'is_active' => true
        ]);

        // Create patients
        $patient = Patient::create([
            'patient_id' => 1,
            'full_name' => 'مريض تجريبي',
            'health_center_code' => 'HC001'
        ]);

        // Create medical records
        $record = MedicalRecord::create([
            'patient_id' => $patient->patient_id,
            'health_center_code' => 'HC001',
            'status_code' => 'active',
            'problem_type_code' => 'test_problem',
            'created_by' => $this->user->user_id,
            'last_modified_by' => $this->user->user_id
        ]);

        // Create transfers
        RecordTransfer::create([
            'record_id' => $record->record_id,
            'sender_id' => $this->user->user_id,
            'recipient_id' => $this->admin->user_id,
            'transfer_notes' => 'ملاحظات تجريبية'
        ]);
    }

    /** @test */
    public function it_can_get_available_reports_as_admin()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/available');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reports',
                    'formats'
                ],
                'message'
            ]);
    }

    /** @test */
    public function it_denies_access_to_available_reports_for_non_admin()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/reports/available');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_generate_daily_transfers_report_in_csv_format_as_admin()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/reports/generate', [
                'report_type' => 'daily_transfers',
                'format' => 'csv',
                'filters' => [
                    'from_date' => now()->subDay()->toDateString(),
                    'to_date' => now()->toDateString()
                ]
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function it_can_generate_daily_transfers_report_in_excel_format_as_admin()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/reports/generate', [
                'report_type' => 'daily_transfers',
                'format' => 'excel',
                'filters' => [
                    'from_date' => now()->subDay()->toDateString(),
                    'to_date' => now()->toDateString()
                ]
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function it_can_generate_daily_transfers_report_in_pdf_format_as_admin()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/reports/generate', [
                'report_type' => 'daily_transfers',
                'format' => 'pdf',
                'filters' => [
                    'from_date' => now()->subDay()->toDateString(),
                    'to_date' => now()->toDateString()
                ]
            ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function it_validates_report_type()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/reports/generate', [
                'report_type' => 'invalid_report',
                'format' => 'csv'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['report_type']);
    }

    /** @test */
    public function it_denies_access_to_generate_reports_for_non_admin()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/reports/generate', [
                'report_type' => 'daily_transfers',
                'format' => 'csv',
                'filters' => [
                    'from_date' => now()->subDay()->toDateString(),
                    'to_date' => now()->toDateString()
                ]
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_date_filters()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/reports/generate', [
                'report_type' => 'daily_transfers',
                'format' => 'csv',
                'filters' => [
                    'from_date' => now()->toDateString(),
                    'to_date' => now()->subDay()->toDateString()
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters.from_date']);
    }
}
