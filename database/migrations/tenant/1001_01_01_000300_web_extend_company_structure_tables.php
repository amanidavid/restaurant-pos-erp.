<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'tenant';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('company_addresses') || $schema->hasColumn('companies', 'registration_number')) {
            return;
        }

        $schema->table('companies', function (Blueprint $table): void {
            $table->string('registration_number', 60)->nullable()->after('trading_name');
            $table->date('opened_on')->nullable()->after('status');
            $table->date('closed_on')->nullable()->after('opened_on');
        });

        $schema->table('departments', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('uuid')->constrained('companies')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->restrictOnDelete();
            $table->text('description')->nullable()->after('name');

            $table->index(['company_id', 'branch_id', 'status'], 'departments_company_branch_status_index');
        });

        $schema->table('cost_centres', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('uuid')->constrained('companies')->restrictOnDelete();
            $table->text('description')->nullable()->after('name');

            $table->index(['company_id', 'status'], 'cost_centres_company_status_index');
        });

        $schema->table('locations', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });

        $schema->create('company_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('address_line_1', 180);
            $table->string('address_line_2', 180)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_region', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->char('country_code', 2);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->index(['company_id', 'type'], 'company_addresses_company_type_index');
        });

        $schema->create('branch_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('name', 160);
            $table->string('email', 254)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->index(['branch_id', 'status', 'type'], 'branch_contacts_branch_status_type_index');
        });

        $schema->create('job_positions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('level_rank')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();

            $table->unique(['company_id', 'name'], 'job_positions_company_name_unique');
            $table->index(['department_id', 'status'], 'job_positions_department_status_index');
        });

        $connection = DB::connection(self::CONNECTION);
        $connection->statement('CREATE UNIQUE INDEX companies_registration_number_unique ON companies (registration_number) WHERE registration_number IS NOT NULL');
        $connection->statement("ALTER TABLE companies ADD CONSTRAINT companies_dates_check CHECK (closed_on IS NULL OR opened_on IS NULL OR closed_on >= opened_on)");
        $connection->statement("ALTER TABLE company_addresses ADD CONSTRAINT company_addresses_type_check CHECK (type IN ('registered', 'billing', 'postal', 'physical'))");
        $connection->statement("ALTER TABLE branch_contacts ADD CONSTRAINT branch_contacts_type_check CHECK (type IN ('general', 'manager', 'billing', 'support'))");
        $connection->statement("ALTER TABLE branch_contacts ADD CONSTRAINT branch_contacts_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("ALTER TABLE job_positions ADD CONSTRAINT job_positions_status_check CHECK (status IN ('active', 'inactive'))");
        $connection->statement("CREATE UNIQUE INDEX company_addresses_one_primary_unique ON company_addresses (company_id) WHERE is_primary = true");
        $connection->statement("CREATE UNIQUE INDEX branch_contacts_one_primary_per_type_unique ON branch_contacts (branch_id, type) WHERE is_primary = true AND status = 'active'");
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $connection = DB::connection(self::CONNECTION);

        $schema->dropIfExists('job_positions');
        $schema->dropIfExists('branch_contacts');
        $schema->dropIfExists('company_addresses');

        $connection->statement('DROP INDEX IF EXISTS companies_registration_number_unique');
        $connection->statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_dates_check');

        $schema->table('locations', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        $schema->table('cost_centres', function (Blueprint $table): void {
            $table->dropIndex('cost_centres_company_status_index');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('description');
        });

        $schema->table('departments', function (Blueprint $table): void {
            $table->dropIndex('departments_company_branch_status_index');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn('description');
        });

        $schema->table('companies', function (Blueprint $table): void {
            $table->dropColumn(['registration_number', 'opened_on', 'closed_on']);
        });
    }
};
