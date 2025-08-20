<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaticDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staticData = [
            // Roles
            ['type' => 'role', 'code' => 'admin', 'label_en' => 'Administrator', 'label_ar' => 'مسؤول', 'description' => 'System administrator with full access'],
            ['type' => 'role', 'code' => 'employee', 'label_en' => 'Employee', 'label_ar' => 'موظف', 'description' => 'General employee with limited access'],

            // Gender
            ['type' => 'gender', 'code' => 'male', 'label_en' => 'Male', 'label_ar' => 'ذكر', 'description' => 'Male gender'],
            ['type' => 'gender', 'code' => 'female', 'label_en' => 'Female', 'label_ar' => 'أنثى', 'description' => 'Female gender'],

            // Health Center Types
            ['type' => 'health_center_type', 'code' => 'nusirat_hospital', 'label_en' => 'Al Nusirat Hospital', 'label_ar' => 'مستشفى العودة النصيرات', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'nusirat_clinic', 'label_en' => 'Al Nusirat Clinic', 'label_ar' => 'مركز العودة النصيرات التعاون', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'asdaa_specialty_center', 'label_en' => 'Asd', 'label_ar' => 'مركز العودة أصداء', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'al_aqsa_specialty_center', 'label_en' => 'Al Aqsa Specialty Center', 'label_ar' => 'مركز العودة خانيونس الأقصى', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'dir_al_balah_specialty_center', 'label_en' => 'Dir Al Balah Specialty Center', 'label_ar' => 'مركز العودة دير البلح', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'al_shateeh_specialty_center', 'label_en' => 'Al Shateen Specialty Center', 'label_ar' => 'مركز العودة  الشاطئ', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'al_nafeq_specialty_center', 'label_en' => 'Al Nafeq Specialty Center', 'label_ar' => 'مركز العودة  النفق', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'jalalah_specialty_center', 'label_en' => 'Jalalah Specialty Center', 'label_ar' => 'مركز العودة  الجلاء', 'description' => ''],
            ['type' => 'health_center_type', 'code' => 'badr_specialty_center', 'label_en' => 'Badr Specialty Center', 'label_ar' => 'مركز العودة  بادر', 'description' => ''],

            // Status
            ['type' => 'status', 'code' => 'initiated', 'label_en' => 'Initiated', 'label_ar' => 'بدأ', 'description' => 'Record workflow initiated'],
            ['type' => 'status', 'code' => 'pending_review', 'label_en' => 'Pending Review', 'label_ar' => 'بانتظار المراجعة', 'description' => 'Record awaiting review'],
            ['type' => 'status', 'code' => 'completed', 'label_en' => 'Completed', 'label_ar' => 'مكتمل', 'description' => 'Record workflow completed'],
      
            // خدمات الجمهور و مختبر و صيدلية و أشعة و أقسام وعمليات و تمريض عيادات خارجية و مخازن و تقارير
            // Problem Types
            ['type' => 'problem_type', 'code' => 'public_services', 'label_en' => 'Public Services', 'label_ar' => 'خدمات الجمهور', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'laboratory', 'label_en' => 'Laboratory', 'label_ar' => ' مختبر', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'pharmacy', 'label_en' => 'Pharmacy', 'label_ar' => ' صيدلية', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'radiology', 'label_en' => 'Radiology', 'label_ar' => ' أشعة', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'sections_and_operations', 'label_en' => 'Sections and Operations', 'label_ar' => 'أقسام وعمليات', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'nursing', 'label_en' => 'Nursing', 'label_ar' => 'تمريض', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'external_clinics', 'label_en' => 'External Clinics', 'label_ar' => 'عيادات خارجية', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'warehouse', 'label_en' => 'Warehouse', 'label_ar' => 'مخازن', 'description' => ''],
            ['type' => 'problem_type', 'code' => 'reports', 'label_en' => 'Reports', 'label_ar' => 'تقارير', 'description' => ''],

            // Danger Levels
            ['type' => 'danger_level', 'code' => 'low', 'label_en' => 'Low Risk', 'label_ar' => 'منخفض', 'description' => ''],
            ['type' => 'danger_level', 'code' => 'medium', 'label_en' => 'Medium Risk', 'label_ar' => 'متوسط', 'description' => ''],
            ['type' => 'danger_level', 'code' => 'high', 'label_en' => 'High Risk', 'label_ar' => 'عالي', 'description' => ''],
            ['type' => 'danger_level', 'code' => 'critical', 'label_en' => 'Critical Risk', 'label_ar' => 'حرج', 'description' => ''],
            ['type' => 'danger_level', 'code' => 'emergency', 'label_en' => 'Emergency', 'label_ar' => 'طارئ', 'description' => ''],

            // Transfer Status Types
            ['type' => 'transfer_status', 'code' => 'not_reviewed', 'label_en' => 'Not Reviewed', 'label_ar' => 'غير مدقق', 'description' => 'Record not reviewed'],
            ['type' => 'transfer_status', 'code' => 'reviewed', 'label_en' => 'Reviewed', 'label_ar' => 'تم التدقيق', 'description' => 'Record reviewed'],
             ];

        foreach ($staticData as $data) {
            DB::table('static_data')->insert([
                'type' => $data['type'],
                'code' => $data['code'],
                'label_en' => $data['label_en'],
                'label_ar' => $data['label_ar'],
                'description' => $data['description'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
