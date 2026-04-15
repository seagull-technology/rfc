<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(null);

        $groups = $this->seedGroups();
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles($permissions);

        $this->seedGroupRoleMap($groups, $roles);
        $this->seedEntities($groups);
        $this->call(ApprovalRoutingSeeder::class);
        $this->seedInitialSuperAdmin($roles);

        $registrar->forgetCachedPermissions();
    }

    /**
     * @return array<string, Group>
     */
    private function seedGroups(): array
    {
        $groups = [];

        foreach ($this->groupDefinitions() as $definition) {
            $groups[$definition['code']] = Group::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );
        }

        return $groups;
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $permissions = [];

        foreach ($this->permissionDefinitions() as $permissionName) {
            $permissions[$permissionName] = Permission::findOrCreate($permissionName, self::GUARD);
        }

        return $permissions;
    }

    /**
     * @param  array<string, Permission>  $permissions
     * @return array<string, Role>
     */
    private function seedRoles(array $permissions): array
    {
        $roles = [];

        foreach ($this->rolePermissionMap() as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => self::GUARD,
                'entity_id' => null,
            ]);

            $role->syncPermissions(array_map(
                static fn (string $permissionName): Permission => $permissions[$permissionName],
                $permissionNames,
            ));

            $roles[$roleName] = $role;
        }

        return $roles;
    }

    /**
     * @param  array<string, Group>  $groups
     * @param  array<string, Role>  $roles
     */
    private function seedGroupRoleMap(array $groups, array $roles): void
    {
        foreach ($this->groupRoleMap() as $groupCode => $roleNames) {
            $groups[$groupCode]->roles()->sync(
                array_map(
                    static fn (string $roleName): int => $roles[$roleName]->getKey(),
                    $roleNames,
                ),
            );
        }
    }

    /**
     * @param  array<string, Group>  $groups
     */
    private function seedEntities(array $groups): void
    {
        foreach ($this->entityDefinitions() as $definition) {
            Entity::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'group_id' => $groups[$definition['group_code']]->getKey(),
                    'parent_entity_id' => null,
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'status' => 'active',
                    'metadata' => $definition['metadata'] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function seedInitialSuperAdmin(array $roles): void
    {
        $adminEntity = Entity::query()->where('code', 'platform-administration')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['email' => env('INITIAL_SUPER_ADMIN_EMAIL', 'superadmin@rfc.local')],
            [
                'name' => env('INITIAL_SUPER_ADMIN_NAME', 'Platform Super Admin'),
                'username' => env('INITIAL_SUPER_ADMIN_USERNAME', 'superadmin'),
                'national_id' => env('INITIAL_SUPER_ADMIN_NATIONAL_ID', '9999999999'),
                'phone' => env('INITIAL_SUPER_ADMIN_PHONE', '0790000099'),
                'status' => 'active',
                'password' => Hash::make(env('INITIAL_SUPER_ADMIN_PASSWORD', 'Admin@12345')),
            ],
        );

        $user->entities()->syncWithoutDetaching([
            $adminEntity->getKey() => [
                'job_title' => 'Platform Super Admin',
                'is_primary' => true,
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($adminEntity->getKey());

        try {
            $user->assignRole($roles['super_admin']);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function groupDefinitions(): array
    {
        return [
            [
                'code' => 'authorities',
                'name_en' => 'Authorities',
                'name_ar' => 'الجهات الحكومية',
                'description' => 'Government authorities and public-sector bodies that review or approve permit requests.',
            ],
            [
                'code' => 'rfc',
                'name_en' => 'RFC',
                'name_ar' => 'الهيئة الملكية الأردنية للأفلام',
                'description' => 'Royal Film Commission internal operational teams.',
            ],
            [
                'code' => 'organizations',
                'name_en' => 'Organizations',
                'name_ar' => 'الشركات والمؤسسات',
                'description' => 'Production companies, fixers, and applicant organizations.',
            ],
            [
                'code' => 'individuals',
                'name_en' => 'Individuals',
                'name_ar' => 'الأفراد',
                'description' => 'Individual applicants and independent filmmakers.',
            ],
            [
                'code' => 'admins',
                'name_en' => 'Admins',
                'name_ar' => 'إدارة المنصة',
                'description' => 'Platform-level administrative and oversight users.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function permissionDefinitions(): array
    {
        return [
            'access.admin-panel',
            'users.view',
            'users.manage',
            'groups.view',
            'groups.manage',
            'entities.view',
            'entities.manage',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'applications.create',
            'applications.view.own',
            'applications.view.entity',
            'applications.view.all',
            'applications.update.own',
            'applications.update.entity',
            'applications.submit',
            'applications.review',
            'applications.approve',
            'applications.reject',
            'applications.request-clarification',
            'applications.assign',
            'workflow.view',
            'workflow.manage',
            'permits.issue',
            'permits.view.own',
            'permits.view.entity',
            'permits.view.all',
            'documents.upload.own',
            'documents.view.own',
            'documents.view.entity',
            'documents.view.all',
            'documents.manage',
            'reports.view.entity',
            'reports.view.all',
            'reports.export',
            'audit.view',
            'settings.manage',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rolePermissionMap(): array
    {
        $allPermissions = $this->permissionDefinitions();

        return [
            'super_admin' => $allPermissions,
            'platform_admin' => [
                'access.admin-panel',
                'users.view',
                'users.manage',
                'groups.view',
                'groups.manage',
                'entities.view',
                'entities.manage',
                'roles.view',
                'roles.manage',
                'permissions.view',
                'permissions.manage',
                'applications.view.all',
                'documents.view.all',
                'documents.manage',
                'workflow.view',
                'workflow.manage',
                'permits.view.all',
                'reports.view.all',
                'reports.export',
                'audit.view',
                'settings.manage',
            ],
            'moderator' => [
                'access.admin-panel',
                'applications.view.all',
                'applications.request-clarification',
                'documents.view.all',
                'workflow.view',
                'permits.view.all',
                'reports.view.all',
                'audit.view',
            ],
            'reporter' => [
                'access.admin-panel',
                'applications.view.all',
                'permits.view.all',
                'reports.view.all',
                'reports.export',
            ],
            'rfc_admin' => [
                'access.admin-panel',
                'applications.view.all',
                'applications.assign',
                'documents.view.all',
                'documents.manage',
                'workflow.view',
                'workflow.manage',
                'permits.view.all',
                'reports.view.all',
                'reports.export',
                'audit.view',
            ],
            'rfc_intake_officer' => [
                'access.admin-panel',
                'applications.view.all',
                'applications.review',
                'applications.request-clarification',
                'applications.assign',
                'documents.view.all',
                'workflow.view',
                'permits.view.all',
            ],
            'rfc_reviewer' => [
                'access.admin-panel',
                'applications.view.all',
                'applications.review',
                'applications.request-clarification',
                'documents.view.all',
                'workflow.view',
                'permits.view.all',
            ],
            'rfc_approver' => [
                'access.admin-panel',
                'applications.view.all',
                'applications.approve',
                'applications.reject',
                'documents.view.all',
                'workflow.view',
                'permits.issue',
                'permits.view.all',
            ],
            'authority_reviewer' => [
                'applications.view.entity',
                'applications.review',
                'applications.request-clarification',
                'documents.view.entity',
                'workflow.view',
                'reports.view.entity',
            ],
            'authority_approver' => [
                'applications.view.entity',
                'applications.review',
                'applications.approve',
                'applications.reject',
                'applications.request-clarification',
                'documents.view.entity',
                'workflow.view',
                'permits.view.entity',
                'reports.view.entity',
            ],
            'applicant_owner' => [
                'applications.create',
                'applications.view.entity',
                'applications.update.entity',
                'applications.submit',
                'documents.upload.own',
                'documents.view.entity',
                'permits.view.entity',
            ],
            'applicant_member' => [
                'applications.create',
                'applications.view.entity',
                'applications.update.entity',
                'documents.upload.own',
                'documents.view.entity',
                'permits.view.entity',
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function groupRoleMap(): array
    {
        return [
            'admins' => [
                'super_admin',
                'platform_admin',
                'moderator',
                'reporter',
            ],
            'rfc' => [
                'rfc_admin',
                'rfc_intake_officer',
                'rfc_reviewer',
                'rfc_approver',
            ],
            'authorities' => [
                'authority_reviewer',
                'authority_approver',
            ],
            'organizations' => [
                'applicant_owner',
                'applicant_member',
            ],
            'individuals' => [
                'applicant_owner',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entityDefinitions(): array
    {
        return [
            [
                'group_code' => 'admins',
                'code' => 'platform-administration',
                'name_en' => 'Platform Administration',
                'name_ar' => 'إدارة المنصة',
                'metadata' => ['seeded' => true],
            ],
            [
                'group_code' => 'rfc',
                'code' => 'rfc-jordan',
                'name_en' => 'Royal Film Commission - Jordan',
                'name_ar' => 'الهيئة الملكية الأردنية للأفلام',
                'metadata' => ['seeded' => true],
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-interior',
                'name_en' => 'Ministry of Interior',
                'name_ar' => 'وزارة الداخلية',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'public-security-directorate',
                'name_en' => 'Public Security Directorate',
                'name_ar' => 'مديرية الأمن العام',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'military-media-directorate',
                'name_en' => 'Military Media Directorate',
                'name_ar' => 'مديرية الإعلام العسكري',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'department-of-antiquities',
                'name_en' => 'Department of Antiquities',
                'name_ar' => 'دائرة الآثار العامة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'jordan-valley-authority',
                'name_en' => 'Jordan Valley Authority',
                'name_ar' => 'سلطة وادي الأردن',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'aqaba-special-economic-zone-authority',
                'name_en' => 'Aqaba Special Economic Zone Authority',
                'name_ar' => 'سلطة منطقة العقبة الاقتصادية الخاصة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'petra-development-and-tourism-region-authority',
                'name_en' => 'Petra Development and Tourism Region Authority',
                'name_ar' => 'سلطة إقليم البتراء التنموي السياحي',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'greater-amman-municipality',
                'name_en' => 'Greater Amman Municipality',
                'name_ar' => 'أمانة عمان الكبرى',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'jordan-customs',
                'name_en' => 'Jordan Customs',
                'name_ar' => 'الجمارك الأردنية',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'royal-society-for-the-conservation-of-nature',
                'name_en' => 'Royal Society for the Conservation of Nature',
                'name_ar' => 'الجمعية الملكية لحماية الطبيعة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-awqaf',
                'name_en' => 'Ministry of Awqaf and Islamic Affairs',
                'name_ar' => 'وزارة الأوقاف والشؤون والمقدسات الإسلامية',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-education',
                'name_en' => 'Ministry of Education',
                'name_ar' => 'وزارة التربية والتعليم',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-higher-education',
                'name_en' => 'Ministry of Higher Education and Scientific Research',
                'name_ar' => 'وزارة التعليم العالي والبحث العلمي',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'jordan-food-and-drug-administration',
                'name_en' => 'Jordan Food and Drug Administration',
                'name_ar' => 'المؤسسة العامة للغذاء والدواء',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'jordan-standards-and-metrology-organization',
                'name_en' => 'Jordan Standards and Metrology Organization',
                'name_ar' => 'مؤسسة المواصفات والمقاييس',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'hejaz-jordan-railway',
                'name_en' => 'Hejaz Jordan Railway Corporation',
                'name_ar' => 'مؤسسة الخط الحديدي الحجازي',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'department-of-palestinian-affairs',
                'name_en' => 'Department of Palestinian Affairs',
                'name_ar' => 'دائرة الشؤون الفلسطينية',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'telecommunications-regulatory-commission',
                'name_en' => 'Telecommunications Regulatory Commission',
                'name_ar' => 'هيئة تنظيم قطاع الاتصالات',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-health',
                'name_en' => 'Ministry of Health',
                'name_ar' => 'وزارة الصحة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-culture',
                'name_en' => 'Ministry of Culture',
                'name_ar' => 'وزارة الثقافة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-justice',
                'name_en' => 'Ministry of Justice',
                'name_ar' => 'وزارة العدل',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-youth',
                'name_en' => 'Ministry of Youth',
                'name_ar' => 'وزارة الشباب',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-public-works-and-housing',
                'name_en' => 'Ministry of Public Works and Housing',
                'name_ar' => 'وزارة الأشغال العامة والإسكان',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-agriculture',
                'name_en' => 'Ministry of Agriculture',
                'name_ar' => 'وزارة الزراعة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-water-and-irrigation',
                'name_en' => 'Ministry of Water and Irrigation',
                'name_ar' => 'وزارة المياه والري',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'ministry-of-energy-and-mineral-resources',
                'name_en' => 'Ministry of Energy and Mineral Resources',
                'name_ar' => 'وزارة الطاقة والثروة المعدنية',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'aqaba-development-corporation',
                'name_en' => 'Aqaba Development Corporation',
                'name_ar' => 'شركة تطوير العقبة',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'aqaba-ports-development-corporation',
                'name_en' => 'Aqaba Ports Development Corporation',
                'name_ar' => 'شركة العقبة لتطوير الموانئ',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'aqaba-port-operation-company',
                'name_en' => 'Aqaba Port Operation Company',
                'name_ar' => 'شركة العقبة لتشغيل الموانئ',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'energy-and-minerals-regulatory-commission',
                'name_en' => 'Energy and Minerals Regulatory Commission',
                'name_ar' => 'هيئة تنظيم الطاقة والمعادن',
            ],
            [
                'group_code' => 'authorities',
                'code' => 'jordanian-company-for-heritage-revival',
                'name_en' => 'Jordanian Company for Heritage Revival',
                'name_ar' => 'الشركة الأردنية لإحياء التراث',
            ],
        ];
    }
}
