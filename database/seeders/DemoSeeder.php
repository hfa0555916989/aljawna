<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\User;
use App\PermissionKey;
use App\Support\SaudiIban;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * بيانات وهمية للعرض المحلي فقط، منفصلة عن DatabaseSeeder ولا تُستدعى تلقائيًا:
 *
 *     php artisan db:seed --class=DemoSeeder
 *
 * كل الأسماء والحسابات والآيبانات هنا واضحة الزيف (بادئة "تجريبي"/"وهمي")، ولا
 * تُطابق أي شخص حقيقي. لا يُنشئ هذا الـ Seeder أي حساب مدير: تُسجَّل المبادرات
 * التجريبية ويعتمدها حساب مشرف تجريبي واحد بصلاحية beneficiaries.manage فقط،
 * لأن هذا العمود في قاعدة البيانات يتطلب مُسجِّلًا (docs/SPEC.md §9 beneficiaries).
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{name: string, bank: string, bank_code: string, account: string, target: string, months: int}>
     */
    private const array BENEFICIARIES = [
        ['name' => 'فهد الوهمي التجريبي الأول', 'bank' => 'بنك تجريبي وهمي أ', 'bank_code' => '80', 'account' => '000000900000001', 'target' => '35000.00', 'months' => 2],
        ['name' => 'سلمان الوهمي التجريبي الثاني', 'bank' => 'بنك تجريبي وهمي ب', 'bank_code' => '15', 'account' => '000000900000002', 'target' => '40000.00', 'months' => 3],
        ['name' => 'ماجد الوهمي التجريبي الثالث', 'bank' => 'بنك تجريبي وهمي أ', 'bank_code' => '80', 'account' => '000000900000003', 'target' => '50000.00', 'months' => 4],
        ['name' => 'تركي الوهمي التجريبي الرابع', 'bank' => 'بنك تجريبي وهمي ج', 'bank_code' => '45', 'account' => '000000900000004', 'target' => '30000.00', 'months' => 1],
        ['name' => 'نايف الوهمي التجريبي الخامس', 'bank' => 'بنك تجريبي وهمي ب', 'bank_code' => '15', 'account' => '000000900000005', 'target' => '45000.00', 'months' => 5],
    ];

    /**
     * جوال تجريبي واضح خارج مدى الأرقام الحقيقية المتداولة (docs/SPEC.md §3).
     */
    private const string DEMO_REGISTRAR_PHONE = '+966500000001';

    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $registrar = $this->demoRegistrar();

        foreach (self::BENEFICIARIES as $data) {
            $this->demoBeneficiary($data, $registrar);
        }

        $this->command?->info('DemoSeeder: بيانات تجريبية وهمية فقط، دون أي حساب مدير.');
    }

    /**
     * @param  array{name: string, bank: string, bank_code: string, account: string, target: string, months: int}  $data
     */
    private function demoBeneficiary(array $data, User $registrar): Beneficiary
    {
        $iban = SaudiIban::fromParts($data['bank_code'], $data['account']);

        return Beneficiary::query()->firstOrCreate(
            ['iban' => $iban],
            [
                'display_name' => $data['name'],
                'account_holder' => $data['name'],
                'bank_name' => $data['bank'],
                'account_number' => $data['account'],
                'target_amount' => $data['target'],
                'target_deadline' => now()->addMonths($data['months'] + 2)->toDateString(),
                'recommended_deadline' => now()->addMonths($data['months'] + 1)->toDateString(),
                'wedding_date' => now()->addMonths($data['months'] + 3)->toDateString(),
                'status' => BeneficiaryStatus::Active,
                'created_by' => $registrar->id,
                'approved_by' => $registrar->id,
                'approved_at' => now(),
            ],
        );
    }

    /**
     * مشرف تجريبي واحد بصلاحية beneficiaries.manage فقط، ليكون مسجِّل المستفيدين
     * التجريبيين ومعتمدهم دون أن يكون مديرًا.
     */
    private function demoRegistrar(): User
    {
        $registrar = User::query()->firstOrCreate(
            ['phone' => self::DEMO_REGISTRAR_PHONE],
            [
                'full_name' => 'مشرف تجريبي لبيانات العرض',
                'password' => Hash::make(Str::random(40)),
                'role' => UserRole::Supervisor,
                'is_active' => true,
                'show_contact' => false,
            ],
        );

        $registrar->syncPermissions([PermissionKey::BeneficiariesManage->value]);

        return $registrar;
    }
}
