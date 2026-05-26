<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * php artisan make:module {Name} [--group=BusinessManagement]
 *
 * Clona el módulo Customer (master template per-tenant, BusinessManagement)
 * hacia un módulo nuevo usando find-replace de identificadores. NO usa stubs
 * reducidos — copia los archivos reales de Customer 1:1 para garantizar paridad
 * de features.
 *
 * Customer trae name + cod + country_id + is_active. Como `cod` y `country_id`
 * son específicos del dominio "cliente comercial" (RUC/RFC/CUIT + país), el
 * scaffold los QUITA y deja en su lugar un campo `description` text nullable
 * que es genérico para cualquier módulo. El usuario después agrega columnas
 * custom del dominio en la migration y model.
 *
 * Operaciones:
 *   1. Validar nombre (PascalCase singular) y que NO sea Customer (master).
 *   2. Calcular reemplazos (singular/plural, PascalCase/snake_case).
 *   3. Verificar que el módulo no exista (idempotencia).
 *   4. Clonar archivos backend (controller, service, model, requests,
 *      jobs, imports, exports, migration, factory).
 *   5. Clonar archivos frontend (pages, components, config).
 *   6. Clonar tests (si existen en Customer).
 *   7. Clonar config + lang (es/en/pt).
 *   8. Append routes al archivo del grupo (crear si no existe).
 *   9. Insertar entradas en config/polymorphic.php y config/purge.php.
 *  10. Auto-registrar fila en system_modules (si la tabla existe).
 *  11. Aplicar post-procesado: quitar cod/country_id, agregar description.
 *  12. Imprimir checklist de pasos manuales.
 *
 * En caso de error a mitad, rollback automático (borrar archivos creados).
 *
 * Ejemplos:
 *   php artisan make:module Patient  --group=HealthManagement
 *   php artisan make:module Doctor   --group=HealthManagement
 *   php artisan make:module Provider --group=BusinessManagement
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
        {name : Nombre del módulo en singular PascalCase (ej. Patient, Doctor)}
        {--group=BusinessManagement : Grupo de namespace/routing (ej. HealthManagement)}';

    protected $description = 'Clona el módulo Customer hacia un módulo nuevo con find-replace 1:1';

    /** Archivos creados durante este run — usado para rollback si algo falla. */
    protected array $createdFiles = [];

    /** Backup de archivos modificados (path => contenido original) para rollback. */
    protected array $modifiedFiles = [];

    /** Tabla de reemplazos calculada. */
    protected array $replacements = [];

    /** Nombre original (PascalCase singular). */
    protected string $module;

    /** Grupo de routing/namespace (PascalCase). */
    protected string $group;

    public function handle(): int
    {
        $this->module = $this->argument('name');
        $this->group  = $this->option('group');

        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $this->module)) {
            $this->error("El nombre debe estar en PascalCase singular (ej. Patient, Doctor). Recibido: {$this->module}");
            return self::FAILURE;
        }

        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $this->group)) {
            $this->error("El grupo debe estar en PascalCase (ej. HealthManagement). Recibido: {$this->group}");
            return self::FAILURE;
        }

        // No permitir override del master template.
        if ($this->module === 'Customer') {
            $this->error("No se puede generar un módulo llamado Customer — ese es el master template.");
            return self::FAILURE;
        }

        $this->replacements = $this->buildReplacements();

        // Idempotencia: si el módulo ya existe, abort sin tocar nada.
        if ($this->moduleAlreadyExists()) {
            $this->error("El módulo {$this->module} ya existe. Para regenerarlo, borra los archivos manualmente primero.");
            return self::FAILURE;
        }

        $this->info("Generando módulo {$this->module} (grupo: {$this->group})");
        $this->newLine();

        try {
            $this->cloneBackend();
            $this->cloneFrontend();
            $this->cloneTests();
            $this->cloneConfigAndLang();
            $this->cloneMigrationAndFactory();
            $this->appendRoutes();
            $this->registerInPolymorphicConfig();
            $this->registerInPurgeConfig();
            $this->registerInSystemModulesTable();
            $this->applyFieldTransformations();
        } catch (\Throwable $e) {
            $this->error("ERROR durante la generación: {$e->getMessage()}");
            $this->warn("Iniciando rollback automático...");
            $this->rollback();
            return self::FAILURE;
        }

        $this->printChecklist();
        return self::SUCCESS;
    }

    /**
     * Calcula todos los reemplazos. ORDEN crítico: plurales primero,
     * sino el replace de `Customer` también afecta `Customers`.
     */
    protected function buildReplacements(): array
    {
        // Names del módulo nuevo.
        $newSingular = $this->module;                       // Patient
        $newPlural   = $this->plural($newSingular);         // Patients
        $newLower    = Str::camel($newSingular);            // patient
        $newLowerPl  = Str::camel($newPlural);              // patients

        // Group names.
        $newGroup    = $this->group;                        // HealthManagement
        $newGroupLower = Str::snake($newGroup);             // health_management

        // ORDEN: primero todo lo plural/largo, después singular/corto.
        // PHP str_replace aplica los reemplazos en el orden del array y NO
        // re-procesa los strings ya reemplazados, así que es seguro.
        return [
            // ─── Request class names (renombrado completo Customer → New) ───
            // Los archivos de Customer ya están renombrados como Store{Customer}Request.php
            // así que aquí solo necesitamos mapear "Customer" en el class name.
            // Estos van primero para evitar el doble-replace del fallback final.
            'BulkDeleteCustomerRequest'     => "BulkDelete{$newSingular}Request",
            'BulkSetActiveCustomerRequest'  => "BulkSetActive{$newSingular}Request",
            'BulkRestoreCustomerRequest'    => "BulkRestore{$newSingular}Request",
            'EditAllUpdateCustomerRequest'  => "EditAllUpdate{$newSingular}Request",
            'ForceDeleteCustomerRequest'    => "ForceDelete{$newSingular}Request",
            'DeleteCustomerRequest'         => "Delete{$newSingular}Request",
            'UpdateCustomerRequest'         => "Update{$newSingular}Request",
            'StoreCustomerRequest'          => "Store{$newSingular}Request",
            'ImportCustomerRequest'         => "Import{$newSingular}Request",

            // ─── Class names específicos (más largos primero) ───
            'BaseCustomerExportJob'      => "Base{$newSingular}ExportJob",
            'BulkCustomersActionJob'     => "Bulk{$newPlural}ActionJob",
            'GenerateCustomersCsvJob'    => "Generate{$newPlural}CsvJob",
            'GenerateCustomersExcelJob'  => "Generate{$newPlural}ExcelJob",
            'GenerateCustomersPdfJob'    => "Generate{$newPlural}PdfJob",
            'GenerateCustomersWordJob'   => "Generate{$newPlural}WordJob",
            'CustomersImportTemplate'    => "{$newPlural}ImportTemplate",
            'CustomersExport'            => "{$newPlural}Export",
            'CustomersImport'            => "{$newPlural}Import",
            'CustomersWord'              => "{$newPlural}Word",
            'CustomerResource'           => "{$newSingular}Resource",
            'CustomerController'         => "{$newSingular}Controller",
            'CustomerService'            => "{$newSingular}Service",
            'CustomerFactory'            => "{$newSingular}Factory",

            // ─── Component / Vue file names (más largos primero) ───
            'CustomersTrashBulkBar'      => "{$newPlural}TrashBulkBar",
            'CustomersBulkDeleteModal'   => "{$newPlural}BulkDeleteModal",
            'CustomersFavoriteCell'      => "{$newPlural}FavoriteCell",
            'CustomersEditAllTable'      => "{$newPlural}EditAllTable",
            'CustomersMobileBottomBar'   => "{$newPlural}MobileBottomBar",
            'CustomersForceDeleteModal'  => "{$newPlural}ForceDeleteModal",
            'CustomersMobileDrawers'     => "{$newPlural}MobileDrawers",
            'CustomersDetailDrawer'      => "{$newPlural}DetailDrawer",
            'CustomersActionsCell'       => "{$newPlural}ActionsCell",
            'CustomersPageHeader'        => "{$newPlural}PageHeader",
            'CustomersEmptyState'        => "{$newPlural}EmptyState",
            'CustomersBulkBar'           => "{$newPlural}BulkBar",

            // ─── Config exports / config filters function names ───
            'customersFilterFields'      => "{$newLowerPl}FilterFields",
            'customersEmptyFilters'      => "{$newLowerPl}EmptyFilters",
            'hydrateCustomersFilters'    => "hydrate{$newPlural}Filters",
            'customersFiltersToQuery'    => "{$newLowerPl}FiltersToQuery",
            'customersFiltersSummary'    => "{$newLowerPl}FiltersSummary",
            'customersTableColumns'      => "{$newLowerPl}TableColumns",
            'customersTrashColumns'      => "{$newLowerPl}TrashColumns",
            'customersExportableColumns' => "{$newLowerPl}ExportableColumns",
            'customersExportEndpoints'   => "{$newLowerPl}ExportEndpoints",
            'customersTourSteps'         => "{$newLowerPl}TourSteps",

            // ─── Plurales (Pages folder, Components folder) ───
            'Customers/'                 => "{$newPlural}/",
            "\\Customers\\"              => "\\{$newPlural}\\",
            'Customers\\'                => "{$newPlural}\\",
            '"Customers"'                => "\"{$newPlural}\"",
            "'Customers'"                => "'{$newPlural}'",
            'Customers '                 => "{$newPlural} ",
            ' Customers'                 => " {$newPlural}",

            // ─── Singulares (path Customer/, namespace \Customer\) ───
            'Customer/'                  => "{$newSingular}/",
            "\\Customer\\"               => "\\{$newSingular}\\",
            'Customer\\'                 => "{$newSingular}\\",
            'Customer::class'            => "{$newSingular}::class",
            '\App\Models\Customer'       => "\\App\\Models\\{$newSingular}",
            'App\Models\Customer'        => "App\\Models\\{$newSingular}",
            'App\Models\\Customer'       => "App\\Models\\{$newSingular}",
            'use App\Models\Customer;'   => "use App\\Models\\{$newSingular};",
            ' Customer '                 => " {$newSingular} ",
            ' Customer,'                 => " {$newSingular},",
            ' Customer;'                 => " {$newSingular};",
            ' Customer$'                 => " {$newSingular}\$",
            '(Customer '                 => "({$newSingular} ",
            'Customer $'                 => "{$newSingular} \$",
            'extends Customer'           => "extends {$newSingular}",

            // ─── Group namespace (PascalCase) ───
            'BusinessManagement\\Customer'  => "{$newGroup}\\{$newSingular}",
            'BusinessManagement\\Customers' => "{$newGroup}\\{$newPlural}",
            'Controllers\\BusinessManagement' => "Controllers\\{$newGroup}",
            'Services\\BusinessManagement'    => "Services\\{$newGroup}",
            'Jobs\\BusinessManagement'        => "Jobs\\{$newGroup}",
            'Imports\\BusinessManagement'     => "Imports\\{$newGroup}",
            'Exports\\BusinessManagement'     => "Exports\\{$newGroup}",
            'Requests\\BusinessManagement'    => "Requests\\{$newGroup}",
            'Tests\\Feature\\BusinessManagement' => "Tests\\Feature\\{$newGroup}",
            'Feature\\BusinessManagement'     => "Feature\\{$newGroup}",
            'namespace App\Http\Controllers\BusinessManagement' => "namespace App\\Http\\Controllers\\{$newGroup}",
            'namespace Tests\Feature\BusinessManagement' => "namespace Tests\\Feature\\{$newGroup}",

            // ─── snake_case plural (table name, route prefix, slug) ───
            // `customers` como table → tiene que ir antes de `customer` singular.
            'customers_tenant_name_unique_active' => "{$newLowerPl}_tenant_name_unique_active",
            'customers_tenant_cod_unique'        => "{$newLowerPl}_tenant_cod_unique",
            'idx_customers_'             => "idx_{$newLowerPl}_",
            'customers.name'             => "{$newLowerPl}.name",
            'customers.cod'              => "{$newLowerPl}.cod",
            'customers.country_id'       => "{$newLowerPl}.country_id",
            'customers.is_active'        => "{$newLowerPl}.is_active",
            'customers.tenant_id'        => "{$newLowerPl}.tenant_id",
            'customers.created_at'       => "{$newLowerPl}.created_at",
            'customers.updated_at'       => "{$newLowerPl}.updated_at",
            'customers.created_by'       => "{$newLowerPl}.created_by",
            'customers.deleted_at'       => "{$newLowerPl}.deleted_at",
            'customers.deleted_by'       => "{$newLowerPl}.deleted_by",
            'customers.id'               => "{$newLowerPl}.id",
            'customers.slug'             => "{$newLowerPl}.slug",
            'customers.show'             => "{$newLowerPl}.show",
            'customers.index'            => "{$newLowerPl}.index",
            'customers.store'            => "{$newLowerPl}.store",
            'customers.update'           => "{$newLowerPl}.update",
            'customers.edit'             => "{$newLowerPl}.edit",
            'customers.create'           => "{$newLowerPl}.create",
            'customers.destroy'          => "{$newLowerPl}.destroy",
            'customers.trash'            => "{$newLowerPl}.trash",
            'customers.restore'          => "{$newLowerPl}.restore",
            'customers.force_delete'     => "{$newLowerPl}.force_delete",
            'customers.export_'          => "{$newLowerPl}.export_",
            'customers.bulk_'            => "{$newLowerPl}.bulk_",
            'customers.import'           => "{$newLowerPl}.import",
            'customers.undo_'            => "{$newLowerPl}.undo_",
            'customers.delete'           => "{$newLowerPl}.delete",
            'customers.deleteSave'       => "{$newLowerPl}.deleteSave",
            'customers.duplicate'        => "{$newLowerPl}.duplicate",
            'customers.edit_all'         => "{$newLowerPl}.edit_all",
            "'customers'"                => "'{$newLowerPl}'",
            '"customers"'                => "\"{$newLowerPl}\"",
            '/customers'                 => "/{$newLowerPl}",
            'customers/'                 => "{$newLowerPl}/",
            'customers:'                 => "{$newLowerPl}:",
            'on customers'               => "on {$newLowerPl}",
            'on `customers`'             => "on `{$newLowerPl}`",
            "Schema::create('customers'" => "Schema::create('{$newLowerPl}'",
            "Schema::table('customers'"  => "Schema::table('{$newLowerPl}'",
            'create_customers_table'     => "create_{$newLowerPl}_table",
            'dropIfExists(\'customers\')' => "dropIfExists('{$newLowerPl}')",
            'lang/customers'             => "lang/{$newLowerPl}",
            'customers.php'              => "{$newLowerPl}.php",
            ' customers.'                => " {$newLowerPl}.",
            '__(\'customers.'            => "__('{$newLowerPl}.",
            '__("customers.'             => "__(\"{$newLowerPl}.",
            't(\'customers.'             => "t('{$newLowerPl}.",
            't("customers.'              => "t(\"{$newLowerPl}.",
            "trans('customers."          => "trans('{$newLowerPl}.",

            // ─── snake_case singular ───
            // `customer` solito como param de route, var, etc.
            'Customer $customer'         => "{$newSingular} \${$newLower}",
            '$customer '                 => "\${$newLower} ",
            '$customer->'                => "\${$newLower}->",
            '$customer,'                 => "\${$newLower},",
            '$customer)'                 => "\${$newLower})",
            '$customer;'                 => "\${$newLower};",
            "'customer'"                 => "'{$newLower}'",
            '"customer"'                 => "\"{$newLower}\"",
            ' customer '                 => " {$newLower} ",
            '/{customer}'                => "/{{$newLower}}",
            '{customer}'                 => "{{$newLower}}",
            'customer:slug'              => "{$newLower}:slug",
            'customer_id'                => "{$newLower}_id",

            // ─── Group URL prefix (snake_case) ───
            'business_management.'       => "{$newGroupLower}.",
            "'business_management'"      => "'{$newGroupLower}'",
            'business_management/'       => "{$newGroupLower}/",
            'prefix(\'business_management\')' => "prefix('{$newGroupLower}')",
            'name(\'business_management.\')'  => "name('{$newGroupLower}.')",

            // ─── auditModule (string en model) ───
            "auditModule = 'customers'"  => "auditModule = '{$newLowerPl}'",
            "'module'         => 'customers'" => "'module'         => '{$newLowerPl}'",
            "'module' => 'customers'"    => "'module' => '{$newLowerPl}'",

            // ─── Test class names ───
            'class CustomerTestCase'     => "class {$newSingular}TestCase",
            'class CustomerCrudTest'     => "class {$newSingular}CrudTest",
            'class CustomerSoftDeleteTest' => "class {$newSingular}SoftDeleteTest",
            'class CustomerPermissionTest' => "class {$newSingular}PermissionTest",
            'class CustomerImportTest'   => "class {$newSingular}ImportTest",
            'class CustomerExportJobTest' => "class {$newSingular}ExportJobTest",
            'class CustomerAuditLogTest' => "class {$newSingular}AuditLogTest",
            'class CustomerPerformanceTest' => "class {$newSingular}PerformanceTest",
            'class CustomerAdvancedFeaturesTest' => "class {$newSingular}AdvancedFeaturesTest",
            'class CustomerDuplicatePreventionTest' => "class {$newSingular}DuplicatePreventionTest",
            'class CustomerTest '        => "class {$newSingular}Test ",
            'class CustomerServiceTest'  => "class {$newSingular}ServiceTest",
            'CustomerTestCase'           => "{$newSingular}TestCase",

            // ─── Fallback: cualquier Customer/customer restante (al final) ───
            // Cuidado: el orden anterior ya capturó los casos seguros.
            // Solo dejamos un fallback genérico para identificadores no listados.
            'Customers'                  => $newPlural,
            'Customer'                   => $newSingular,
            'customers'                  => $newLowerPl,
            'customer'                   => $newLower,
        ];
    }

    protected function plural(string $singular): string
    {
        // Casos comunes en español que Doctrine inflector no pluraliza bien.
        $map = [
            'Patient'     => 'Patients',
            'Doctor'      => 'Doctors',
            'Transformer' => 'Transformers',
            'Provider'    => 'Providers',
            'Paciente'    => 'Pacientes',
            'Producto'    => 'Productos',
            'Cliente'     => 'Clientes',
        ];
        if (isset($map[$singular])) {
            return $map[$singular];
        }
        return Str::plural($singular);
    }

    protected function moduleAlreadyExists(): bool
    {
        $checks = [
            "app/Models/{$this->module}.php",
            "app/Http/Controllers/{$this->group}/{$this->module}Controller.php",
            "app/Services/{$this->group}/{$this->module}Service.php",
            "config/" . $this->snakePlural() . ".php",
            "resources/js/Pages/{$this->plural($this->module)}/Index.vue",
        ];
        foreach ($checks as $rel) {
            if (File::exists(base_path($rel))) {
                $this->error("Archivo ya existe: {$rel}");
                return true;
            }
        }
        return false;
    }

    protected function snakePlural(): string
    {
        return Str::snake($this->plural($this->module));
    }

    /**
     * Clona un archivo aplicando find-replace.
     */
    protected function cloneFile(string $sourceRel, string $destRel): void
    {
        $sourceAbs = base_path($sourceRel);
        $destAbs   = base_path($destRel);

        if (!File::exists($sourceAbs)) {
            throw new \RuntimeException("Source no existe: {$sourceRel}");
        }
        if (File::exists($destAbs)) {
            $this->warn("  SKIP (ya existe): {$destRel}");
            return;
        }

        $content = file_get_contents($sourceAbs);
        $content = str_replace(array_keys($this->replacements), array_values($this->replacements), $content);

        File::ensureDirectoryExists(dirname($destAbs));
        // Escritura sin BOM — crítico en Windows PowerShell.
        file_put_contents($destAbs, $content);

        $this->createdFiles[] = $destAbs;
        $this->line("  CREADO: {$destRel}");
    }

    protected function cloneBackend(): void
    {
        $this->info('Backend...');
        $singular = $this->module;
        $plural   = $this->plural($singular);
        $group    = $this->group;

        // Controller.
        $this->cloneFile(
            'app/Http/Controllers/BusinessManagement/CustomerController.php',
            "app/Http/Controllers/{$group}/{$singular}Controller.php"
        );

        // Service.
        $this->cloneFile(
            'app/Services/BusinessManagement/CustomerService.php',
            "app/Services/{$group}/{$singular}Service.php"
        );

        // Model.
        $this->cloneFile(
            'app/Models/Customer.php',
            "app/Models/{$singular}.php"
        );

        // NOTA: NO clonamos CustomerResource. La capa API (Resource + ApiController
        // + rutas en routes/api.php) es opcional y específica del módulo — se
        // implementa a mano post-scaffold solo si el módulo va a exponerse via API.
        // Los módulos generados por defecto son web-only (Inertia).

        // FormRequests (9 archivos).
        // Customer ya los tiene renombrados como Store{Customer}Request.php,
        // los reemplazos del array buildReplacements() se encargan del rename
        // a Store{NewName}Request.
        $requests = [
            'StoreCustomerRequest.php'         => "Store{$singular}Request.php",
            'UpdateCustomerRequest.php'        => "Update{$singular}Request.php",
            'DeleteCustomerRequest.php'        => "Delete{$singular}Request.php",
            'ForceDeleteCustomerRequest.php'   => "ForceDelete{$singular}Request.php",
            'BulkDeleteCustomerRequest.php'    => "BulkDelete{$singular}Request.php",
            'BulkSetActiveCustomerRequest.php' => "BulkSetActive{$singular}Request.php",
            'BulkRestoreCustomerRequest.php'   => "BulkRestore{$singular}Request.php",
            'EditAllUpdateCustomerRequest.php' => "EditAllUpdate{$singular}Request.php",
            'ImportCustomerRequest.php'        => "Import{$singular}Request.php",
        ];
        foreach ($requests as $src => $dst) {
            $srcRel = "app/Http/Requests/BusinessManagement/Customer/{$src}";
            if (File::exists(base_path($srcRel))) {
                $this->cloneFile(
                    $srcRel,
                    "app/Http/Requests/{$group}/{$singular}/{$dst}"
                );
            }
        }

        // Imports.
        $this->cloneFile(
            'app/Imports/BusinessManagement/Customers/CustomersImport.php',
            "app/Imports/{$group}/{$plural}/{$plural}Import.php"
        );

        // Exports (3 archivos).
        $exports = [
            'CustomersExport.php'         => "{$plural}Export.php",
            'CustomersWord.php'           => "{$plural}Word.php",
            'CustomersImportTemplate.php' => "{$plural}ImportTemplate.php",
        ];
        foreach ($exports as $src => $dst) {
            $srcRel = "app/Exports/BusinessManagement/Customers/{$src}";
            if (File::exists(base_path($srcRel))) {
                $this->cloneFile(
                    $srcRel,
                    "app/Exports/{$group}/{$plural}/{$dst}"
                );
            }
        }

        // Jobs (6 archivos).
        $jobs = [
            'BaseCustomerExportJob.php'    => "Base{$singular}ExportJob.php",
            'BulkCustomersActionJob.php'   => "Bulk{$plural}ActionJob.php",
            'GenerateCustomersCsvJob.php'  => "Generate{$plural}CsvJob.php",
            'GenerateCustomersExcelJob.php' => "Generate{$plural}ExcelJob.php",
            'GenerateCustomersPdfJob.php'  => "Generate{$plural}PdfJob.php",
            'GenerateCustomersWordJob.php' => "Generate{$plural}WordJob.php",
        ];
        foreach ($jobs as $src => $dst) {
            $srcRel = "app/Jobs/BusinessManagement/Customers/{$src}";
            if (File::exists(base_path($srcRel))) {
                $this->cloneFile(
                    $srcRel,
                    "app/Jobs/{$group}/{$plural}/{$dst}"
                );
            }
        }
    }

    protected function cloneFrontend(): void
    {
        $this->info('Frontend...');
        $plural = $this->plural($this->module);

        // Pages (6 archivos: Index, Show, Form, Delete, Trash, EditAll).
        foreach (['Index', 'Show', 'Form', 'Delete', 'Trash', 'EditAll'] as $page) {
            $src = "resources/js/Pages/Customers/{$page}.vue";
            $dst = "resources/js/Pages/{$plural}/{$page}.vue";
            if (File::exists(base_path($src))) {
                $this->cloneFile($src, $dst);
            }
        }

        // Page configs (5 archivos).
        foreach (['columns', 'filters', 'exports', 'tour', 'trashColumns'] as $cfg) {
            $src = "resources/js/Pages/Customers/config/{$cfg}.js";
            $dst = "resources/js/Pages/{$plural}/config/{$cfg}.js";
            if (File::exists(base_path($src))) {
                $this->cloneFile($src, $dst);
            }
        }

        // Components (todos los Customers/Customers*.vue).
        $componentDir = base_path('resources/js/Components/Customers');
        if (File::isDirectory($componentDir)) {
            foreach (File::files($componentDir) as $file) {
                $filename = $file->getFilename(); // CustomersBulkBar.vue
                // Renombrar Customers* a {Plural}*.
                $newFilename = str_replace('Customers', $plural, $filename);
                $src = "resources/js/Components/Customers/{$filename}";
                $dst = "resources/js/Components/{$plural}/{$newFilename}";
                $this->cloneFile($src, $dst);
            }
        }
    }

    protected function cloneTests(): void
    {
        $this->info('Tests...');
        $singular = $this->module;
        $plural   = $this->plural($singular);
        $group    = $this->group;

        // Feature tests (si existen — Customer puede no tener tests todavía).
        $testDir = base_path('tests/Feature/BusinessManagement/Customers');
        if (File::isDirectory($testDir)) {
            foreach (File::files($testDir) as $file) {
                $filename = $file->getFilename();
                // CustomerCrudTest.php → PatientCrudTest.php
                $newFilename = preg_replace('/^Customer/', $singular, $filename);
                $src = "tests/Feature/BusinessManagement/Customers/{$filename}";
                $dst = "tests/Feature/{$group}/{$plural}/{$newFilename}";
                $this->cloneFile($src, $dst);
            }
        } else {
            $this->line('  SKIP tests/Feature/BusinessManagement/Customers (no existe — Customer aun sin tests)');
        }

        // Unit tests.
        if (File::exists(base_path('tests/Unit/Models/CustomerTest.php'))) {
            $this->cloneFile(
                'tests/Unit/Models/CustomerTest.php',
                "tests/Unit/Models/{$singular}Test.php"
            );
        }
        if (File::exists(base_path('tests/Unit/Services/CustomerServiceTest.php'))) {
            $this->cloneFile(
                'tests/Unit/Services/CustomerServiceTest.php',
                "tests/Unit/Services/{$singular}ServiceTest.php"
            );
        }
    }

    protected function cloneConfigAndLang(): void
    {
        $this->info('Config + i18n...');
        $newLowerPl = $this->snakePlural();

        // config.
        if (File::exists(base_path('config/customers.php'))) {
            $this->cloneFile('config/customers.php', "config/{$newLowerPl}.php");
        }

        // lang es / en. (pt deshabilitado — se completa al final del proyecto)
        foreach (['es', 'en'] as $locale) {
            $src = "resources/lang/{$locale}/customers.php";
            if (File::exists(base_path($src))) {
                $this->cloneFile($src, "resources/lang/{$locale}/{$newLowerPl}.php");
            }
        }
    }

    protected function cloneMigrationAndFactory(): void
    {
        $this->info('Database...');
        $newLowerPl = $this->snakePlural();
        $singular   = $this->module;

        // Migration con timestamp nuevo.
        $sourceMigration = $this->findCustomersMigration();
        if ($sourceMigration === null) {
            throw new \RuntimeException("No se encontró migration create_customers_table");
        }
        $timestamp = date('Y_m_d_His');
        $destMigration = "database/migrations/{$timestamp}_create_{$newLowerPl}_table.php";
        $this->cloneFile($sourceMigration, $destMigration);

        // Factory.
        if (File::exists(base_path('database/factories/CustomerFactory.php'))) {
            $this->cloneFile(
                'database/factories/CustomerFactory.php',
                "database/factories/{$singular}Factory.php"
            );
        }
    }

    protected function findCustomersMigration(): ?string
    {
        $dir = base_path('database/migrations');
        foreach (File::files($dir) as $file) {
            if (str_contains($file->getFilename(), 'create_customers_table')) {
                return 'database/migrations/' . $file->getFilename();
            }
        }
        return null;
    }

    /**
     * Append a routes/{group}.php un bloque de rutas para el módulo nuevo.
     * Si el archivo no existe, lo crea con el scaffold base.
     */
    protected function appendRoutes(): void
    {
        $this->info('Routes...');
        $singular = $this->module;
        $plural   = $this->plural($singular);
        $group    = $this->group;
        $groupSnake = Str::snake($group);
        $newLowerPl = $this->snakePlural();
        $newLower   = Str::camel($singular);

        $routeFile = base_path("routes/{$groupSnake}.php");
        $newFile   = !File::exists($routeFile);

        $controllerFqcn = "App\\Http\\Controllers\\{$group}\\{$singular}Controller";

        if ($newFile) {
            // Crear archivo con scaffold base.
            $header = <<<PHP
<?php

use Illuminate\Support\Facades\Route;
use {$controllerFqcn};

/*
|--------------------------------------------------------------------------
| {$group}
|--------------------------------------------------------------------------
| Modulos generados con make:module. Cada modulo se gobierna por permisos
| Spatie: {$newLowerPl}.view, {$newLowerPl}.create, etc.
|
| ORDEN DE RUTAS CRITICO: las rutas con paths estaticos ({$newLowerPl}/create,
| {$newLowerPl}/trash, {$newLowerPl}/export_*) DEBEN ir ANTES que {$newLowerPl}/{{$newLower}}.
*/

Route::prefix('{$groupSnake}')->name('{$groupSnake}.')->group(function () {

PHP;
            file_put_contents($routeFile, $header);
            $this->createdFiles[] = $routeFile;
            $this->line("  CREADO: routes/{$groupSnake}.php");
        } else {
            $this->modifiedFiles[$routeFile] = file_get_contents($routeFile);
        }

        $block = $this->buildRoutesBlock();

        if ($newFile) {
            // Cerramos el group function.
            file_put_contents($routeFile, $block . "\n});\n", FILE_APPEND);
        } else {
            // Insertar antes del último `});` que cierra el `Route::prefix(...)` group.
            $existing = file_get_contents($routeFile);
            // Buscar el último `});` del archivo.
            $lastClose = strrpos($existing, '});');
            if ($lastClose === false) {
                // Archivo no tiene el patrón esperado — append al final.
                file_put_contents($routeFile, "\n" . $block . "\n", FILE_APPEND);
            } else {
                $before = substr($existing, 0, $lastClose);
                $after  = substr($existing, $lastClose);
                file_put_contents($routeFile, $before . "\n" . $block . "\n" . $after);
            }

            // Insertar use statement si no está.
            $current = file_get_contents($routeFile);
            if (!str_contains($current, "use {$controllerFqcn};")) {
                $current = preg_replace(
                    '/(use Illuminate\\\\Support\\\\Facades\\\\Route;)/',
                    "$1\nuse {$controllerFqcn};",
                    $current,
                    1
                );
                file_put_contents($routeFile, $current);
            }

            $this->line("  MODIFICADO: routes/{$groupSnake}.php (block appended)");
        }
    }

    protected function buildRoutesBlock(): string
    {
        $singular   = $this->module;
        $plural     = $this->plural($singular);
        $newLowerPl = $this->snakePlural();
        $newLower   = Str::camel($singular);
        $groupSnake = Str::snake($this->group);
        $ctrl       = "{$singular}Controller";

        return <<<PHP

    // ── {$plural} ──
    // Bloque generado por make:module. Reordena o ajusta permisos según tu dominio.

    // 1) Trash + restore + force_delete (super only — defense in depth)
    Route::middleware('role:super')->group(function () {
        Route::get('{$newLowerPl}/trash',                  [{$ctrl}::class, 'trash'])->name('{$newLowerPl}.trash');
        Route::post('{$newLowerPl}/bulk_restore',          [{$ctrl}::class, 'bulkRestore'])->name('{$newLowerPl}.bulk_restore');
        Route::post('{$newLowerPl}/{slug}/restore',        [{$ctrl}::class, 'restore'])->name('{$newLowerPl}.restore');
        Route::get('{$newLowerPl}/{slug}/restore',         fn () => redirect()->route('{$groupSnake}.{$newLowerPl}.trash'));
        Route::delete('{$newLowerPl}/{slug}/force_delete', [{$ctrl}::class, 'forceDelete'])->name('{$newLowerPl}.force_delete');
    });

    // 2) Exports (gated por plan_feature por formato)
    Route::middleware('permission:{$newLowerPl}.view')->group(function () {
        Route::middleware(['throttle:5,1', 'plan_feature:export_excel'])
            ->post('{$newLowerPl}/export_excel', [{$ctrl}::class, 'exportExcel'])->name('{$newLowerPl}.export_excel');
        Route::middleware(['throttle:5,1', 'plan_feature:export_pdf'])
            ->post('{$newLowerPl}/export_pdf',   [{$ctrl}::class, 'exportPdf'])->name('{$newLowerPl}.export_pdf');
        Route::middleware(['throttle:5,1', 'plan_feature:export_word'])
            ->post('{$newLowerPl}/export_word',  [{$ctrl}::class, 'exportWord'])->name('{$newLowerPl}.export_word');
        Route::middleware('throttle:5,1')
            ->post('{$newLowerPl}/export_csv',   [{$ctrl}::class, 'exportCsv'])->name('{$newLowerPl}.export_csv');
    });

    // 3) Imports
    Route::middleware(['permission:{$newLowerPl}.create', 'plan_feature:bulk_operations'])->group(function () {
        Route::post('{$newLowerPl}/import',          [{$ctrl}::class, 'import'])->name('{$newLowerPl}.import');
        Route::get('{$newLowerPl}/import_template',  [{$ctrl}::class, 'importTemplate'])->name('{$newLowerPl}.import_template');
    });

    // 4) Bulk operations
    Route::middleware(['permission:{$newLowerPl}.delete', 'plan_feature:bulk_operations', 'throttle:10,1'])->group(function () {
        Route::post('{$newLowerPl}/bulk_delete',     [{$ctrl}::class, 'bulkDelete'])->name('{$newLowerPl}.bulk_delete');
        Route::post('{$newLowerPl}/bulk_set_active', [{$ctrl}::class, 'bulkSetActive'])->name('{$newLowerPl}.bulk_set_active');
    });

    // Undo del ultimo borrado (60s window)
    Route::middleware('permission:{$newLowerPl}.delete')->group(function () {
        Route::post('{$newLowerPl}/undo_last_delete', [{$ctrl}::class, 'undoLastDelete'])->name('{$newLowerPl}.undo_last_delete');
    });

    // Edit All
    Route::middleware('permission:{$newLowerPl}.edit')->group(function () {
        Route::get('{$newLowerPl}/edit_all',         [{$ctrl}::class, 'editAll'])->name('{$newLowerPl}.edit_all');
        Route::post('{$newLowerPl}/edit_all/update', [{$ctrl}::class, 'editAllUpdate'])->name('{$newLowerPl}.edit_all.update');
    });

    // 5) CRUD principal — paths estaticos PRIMERO.
    Route::middleware('permission:{$newLowerPl}.create')->group(function () {
        Route::get('{$newLowerPl}/create', [{$ctrl}::class, 'create'])->name('{$newLowerPl}.create');
        Route::post('{$newLowerPl}',       [{$ctrl}::class, 'store'])->name('{$newLowerPl}.store');
        Route::post('{$newLowerPl}/{{$newLower}}/duplicate', [{$ctrl}::class, 'duplicate'])->name('{$newLowerPl}.duplicate');
    });

    Route::middleware('permission:{$newLowerPl}.view')->group(function () {
        Route::get('{$newLowerPl}',                [{$ctrl}::class, 'index'])->name('{$newLowerPl}.index');
        Route::get('{$newLowerPl}/{{$newLower}}',  [{$ctrl}::class, 'show'])->name('{$newLowerPl}.show');
    });
    Route::middleware('permission:{$newLowerPl}.edit')->group(function () {
        Route::get('{$newLowerPl}/{{$newLower}}/edit', [{$ctrl}::class, 'edit'])->name('{$newLowerPl}.edit');
        Route::put('{$newLowerPl}/{{$newLower}}',      [{$ctrl}::class, 'update'])->name('{$newLowerPl}.update');
    });
    Route::middleware('permission:{$newLowerPl}.delete')->group(function () {
        Route::get('{$newLowerPl}/{{$newLower}}/delete',        [{$ctrl}::class, 'delete'])->name('{$newLowerPl}.delete');
        Route::delete('{$newLowerPl}/{{$newLower}}/deleteSave', [{$ctrl}::class, 'deleteSave'])->name('{$newLowerPl}.deleteSave');
    });
PHP;
    }

    protected function registerInPolymorphicConfig(): void
    {
        $path = base_path('config/polymorphic.php');
        if (!File::exists($path)) return;

        $content = file_get_contents($path);
        $newLowerPl = $this->snakePlural();
        $singular   = $this->module;
        $groupSnake = Str::snake($this->group);

        // Si ya está registrado (uso real, no comentario), skip.
        if (preg_match("/'{$newLowerPl}'\\s*=>\\s*\\[\\s*\\n/m", $content)) {
            $this->line("  SKIP polymorphic ({$newLowerPl} ya registrado)");
            return;
        }

        $entry = "'{$newLowerPl}' => [\n" .
                 "            'model'      => \\App\\Models\\{$singular}::class,\n" .
                 "            'show_route' => '{$groupSnake}.{$newLowerPl}.show',\n" .
                 "        ],\n        ";

        $marker = "// Agrega modulos nuevos aqui";
        if (str_contains($content, $marker)) {
            $this->modifiedFiles[$path] = $content;
            $new = str_replace($marker, $entry . $marker, $content);
            file_put_contents($path, $new);
            $this->line("  MODIFICADO: config/polymorphic.php (entry agregada)");
        } else {
            $this->modifiedFiles[$path] = $content;
            $pattern = "/(\\s+\\],\\s*\\];\\s*$)/";
            $new = preg_replace($pattern, "\n" . $entry . "    ],\n];\n", $content, 1);
            file_put_contents($path, $new);
            $this->line("  MODIFICADO: config/polymorphic.php (entry agregada al final)");
        }
    }

    protected function registerInPurgeConfig(): void
    {
        $path = base_path('config/purge.php');
        if (!File::exists($path)) return;

        $content = file_get_contents($path);
        $newLowerPl = $this->snakePlural();
        $singular   = $this->module;

        if (preg_match("/'{$newLowerPl}'\\s*=>\\s*\\[\\s*\\n/m", $content)) {
            $this->line("  SKIP purge ({$newLowerPl} ya registrado)");
            return;
        }

        $entry = "'{$newLowerPl}' => [\n" .
                 "            'model' => \\App\\Models\\{$singular}::class,\n" .
                 "            'days'  => 90,\n" .
                 "        ],\n        ";

        $marker = "// Suma modulos nuevos aqui:";
        if (str_contains($content, $marker)) {
            $this->modifiedFiles[$path] = $content;
            $new = str_replace($marker, $entry . $marker, $content);
            file_put_contents($path, $new);
            $this->line("  MODIFICADO: config/purge.php (entry agregada)");
        } else {
            $this->modifiedFiles[$path] = $content;
            $pattern = "/(\\s+\\],\\s*\\];\\s*$)/";
            $new = preg_replace($pattern, "\n" . $entry . "    ],\n];\n", $content, 1);
            file_put_contents($path, $new);
            $this->line("  MODIFICADO: config/purge.php (entry agregada al final)");
        }
    }

    /**
     * Auto-registra el módulo en system_modules (si la tabla existe).
     * Es idempotente — si la fila para el módulo nuevo ya existe, skip.
     */
    protected function registerInSystemModulesTable(): void
    {
        // Guard contra BD inaccesible (archivo sqlite no existe, pgsql down,
        // credenciales mal, etc.). El scaffold debe poder generar archivos
        // incluso sin BD — el usuario corre migrate después y registra el
        // módulo manualmente vía seeder o UI.
        try {
            $hasTable = Schema::hasTable('system_modules');
        } catch (\Throwable $e) {
            $this->warn('Sin conexion a BD — skip auto-registro en system_modules.');
            $this->warn('  Reason: ' . $e->getMessage());
            $this->warn('  Tras crear/migrar la BD, registra el modulo manualmente:');
            $this->warn("  php artisan tinker --execute=\"DB::table('system_modules')->insert(['slug' => Str::random(22), 'name' => '{$this->module}', 'permission_key' => '{$this->snakePlural()}.view', 'is_active' => true, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()]);\"");
            return;
        }

        if (!$hasTable) {
            $this->warn('Tabla system_modules no existe — skip auto-registro.');
            return;
        }

        $newSingular = $this->module;

        // Idempotente: skip si ya existe una fila con ese name.
        $exists = DB::table('system_modules')->where('name', $newSingular)->exists();
        if ($exists) {
            $this->line("  SKIP system_modules ({$newSingular} ya registrado)");
            return;
        }

        $slug = Str::random(22);
        $newLowerPl = $this->snakePlural();

        // permission_key es el SLUG del modulo (sin sufijo de accion). El
        // seeder de RolesAndPermissions appendea `.view`, `.create`, etc.
        // Ej: 'companies' → 'companies.view', 'companies.create', etc.
        $permissionKey = $newLowerPl;

        // Si el permission_key ya existe (segunda corrida del scaffold con el
        // mismo modulo limpiado), skipear para no chocar contra la unique.
        if (DB::table('system_modules')->where('permission_key', $permissionKey)->exists()) {
            $this->line("  SKIP system_modules ({$permissionKey} ya existe)");
            return;
        }

        DB::table('system_modules')->insert([
            'slug'           => $slug,
            'name'           => $newSingular,
            'permission_key' => $permissionKey,
            'is_active'      => true,
            'created_by'     => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $this->line("  CREADO en system_modules: {$newSingular} (permission_key={$permissionKey})");
    }

    /**
     * Post-procesado vía markers `@scaffold:*` en el código fuente de Customer.
     *
     * Customer master tiene bloques marcados con `// @scaffold:remove-begin X`
     * ... `// @scaffold:remove-end` (y variantes HTML/single-line) que aplican
     * solo al dominio comercial (cod, country_id). El scaffold los borra para
     * dejar un módulo genérico con solo `name` + `description`.
     *
     * Anchors `// @scaffold:anchor X` se reemplazan con snippets de la tabla
     * `buildAnchorInserts()` para insertar el campo `description` en las
     * ubicaciones correctas (migration, model, form, etc.).
     *
     * Si Customer no tiene markers (no es un módulo derivable), no hace nada.
     */
    protected function applyFieldTransformations(): void
    {
        $this->info('Procesando markers @scaffold:* en archivos clonados...');

        $singular   = $this->module;
        $plural     = $this->plural($singular);
        $newLower   = Str::camel($singular);
        $newLowerPl = $this->snakePlural();
        $group      = $this->group;

        $inserts = $this->buildAnchorInserts();

        $files = [
            // Migration (auto-detectada por timestamp).
            $this->findGeneratedMigration($newLowerPl),

            // Backend core.
            base_path("app/Models/{$singular}.php"),
            base_path("database/factories/{$singular}Factory.php"),
            base_path("app/Http/Controllers/{$group}/{$singular}Controller.php"),
            base_path("app/Services/{$group}/{$singular}Service.php"),

            // FormRequests con cod/country_id rules.
            base_path("app/Http/Requests/{$group}/{$singular}/Store{$singular}Request.php"),
            base_path("app/Http/Requests/{$group}/{$singular}/Update{$singular}Request.php"),

            // Vue pages.
            base_path("resources/js/Pages/{$plural}/Index.vue"),
            base_path("resources/js/Pages/{$plural}/Form.vue"),
            base_path("resources/js/Pages/{$plural}/Show.vue"),
            base_path("resources/js/Pages/{$plural}/Delete.vue"),

            // Vue components.
            base_path("resources/js/Components/{$plural}/{$plural}DetailDrawer.vue"),
            base_path("resources/js/Components/{$plural}/{$plural}EditAllTable.vue"),

            // Page configs.
            base_path("resources/js/Pages/{$plural}/config/columns.js"),
            base_path("resources/js/Pages/{$plural}/config/filters.js"),
            base_path("resources/js/Pages/{$plural}/config/exports.js"),
            base_path("resources/js/Pages/{$plural}/config/trashColumns.js"),

            // Jobs de export.
            base_path("app/Jobs/{$group}/{$plural}/Base{$singular}ExportJob.php"),
            base_path("app/Jobs/{$group}/{$plural}/Generate{$plural}CsvJob.php"),
            base_path("app/Jobs/{$group}/{$plural}/Generate{$plural}PdfJob.php"),

            // Import + Exports + Template.
            base_path("app/Imports/{$group}/{$plural}/{$plural}Import.php"),
            base_path("app/Exports/{$group}/{$plural}/{$plural}Export.php"),
            base_path("app/Exports/{$group}/{$plural}/{$plural}Word.php"),
            base_path("app/Exports/{$group}/{$plural}/{$plural}ImportTemplate.php"),

            // Tests.
            base_path("tests/Feature/{$group}/{$plural}/{$singular}CrudTest.php"),
            base_path("tests/Feature/{$group}/{$plural}/{$singular}ExportTest.php"),
        ];

        foreach (array_filter($files) as $file) {
            if (File::exists($file)) {
                $this->processFile($file, $inserts);
            }
        }

        // Lang files: insert localized 'description' label per locale.
        $langLabels = ['es' => 'Descripción', 'en' => 'Description'];
        foreach ($langLabels as $locale => $label) {
            $langFile = base_path("resources/lang/{$locale}/{$newLowerPl}.php");
            if (!File::exists($langFile)) continue;
            $localInserts = $inserts;
            $localInserts['description-lang'] = "    'description' => '{$label}',";
            $this->processFile($langFile, $localInserts);
        }
    }

    /**
     * Procesa un archivo: strip markers + insert anchors + post-process per file.
     */
    protected function processFile(string $file, array $anchorInserts): void
    {
        $content  = file_get_contents($file);
        $original = $content;

        $content = $this->stripMarkers($content);
        $content = $this->insertAnchors($content, $anchorInserts);
        $content = $this->fileSpecificPostProcess($file, $content);

        if ($content !== $original) {
            file_put_contents($file, $content);
            $this->line('  PROCESADO: ' . $this->relPath($file));
        }
    }

    /**
     * Elimina contenido marcado con @scaffold:remove-* directives.
     *
     * Soporta:
     *  - Bloque PHP/JS:  `// @scaffold:remove-begin X` ... `// @scaffold:remove-end`
     *  - Bloque HTML:    `<!-- @scaffold:remove-begin X -->` ... `<!-- @scaffold:remove-end -->`
     *  - Línea single:   cualquier línea que contenga `@scaffold:remove-line`
     */
    protected function stripMarkers(string $content): string
    {
        // 1. HTML-style block markers (Vue templates).
        $content = preg_replace(
            '/[\t ]*<!--\s*@scaffold:remove-begin\s+[\w-]+\s*-->[\s\S]*?<!--\s*@scaffold:remove-end\s*-->\s*\n?/',
            '',
            $content
        );

        // 2. PHP/JS-style block markers.
        $content = preg_replace(
            '/[\t ]*\/\/\s*@scaffold:remove-begin\s+[\w-]+[\s\S]*?\/\/\s*@scaffold:remove-end\s*\n?/',
            '',
            $content
        );

        // 3. Single-line markers — borra cualquier línea con @scaffold:remove-line.
        $content = preg_replace('/^[^\n]*@scaffold:remove-line[^\n]*\n/m', '', $content);

        return $content;
    }

    /**
     * Inserta snippets en los puntos marcados con @scaffold:anchor X.
     * Anchors no resueltos (sin entry en el inserts map) se borran al final.
     */
    protected function insertAnchors(string $content, array $anchorInserts): string
    {
        foreach ($anchorInserts as $name => $snippet) {
            $escaped = preg_quote($name, '/');

            // PHP/JS-style anchor.
            $content = preg_replace(
                '/[\t ]*\/\/\s*@scaffold:anchor\s+' . $escaped . '\s*$/m',
                $snippet,
                $content
            );

            // HTML-style anchor.
            $content = preg_replace(
                '/[\t ]*<!--\s*@scaffold:anchor\s+' . $escaped . '\s*-->/',
                $snippet,
                $content
            );
        }

        // Limpieza: anchors huérfanos (sin snippet) se borran completos.
        $content = preg_replace('/^[\t ]*\/\/\s*@scaffold:anchor[^\n]*\n/m', '', $content);
        $content = preg_replace('/^[\t ]*<!--\s*@scaffold:anchor[^\n]*-->\s*\n/m', '', $content);

        return $content;
    }

    /**
     * Post-procesado específico por archivo. Casos donde markers no aplican
     * limpio (ej: cambios de column letter en ImportTemplate).
     */
    protected function fileSpecificPostProcess(string $file, string $content): string
    {
        $basename = basename($file);

        // ImportTemplate: tras quitar cod (col B) y country_iso (col C), los
        // headers + columnas pasan de 4 (A-D) a 3 (A-C). Ajustamos las
        // referencias de letras.
        if (str_ends_with($basename, 'ImportTemplate.php')) {
            $content = str_replace(
                [
                    "'A1:D1'",
                    "['A', 'B', 'C', 'D']",
                    "getComment('D1')",
                ],
                [
                    "'A1:C1'",
                    "['A', 'B', 'C']",
                    "getComment('C1')",
                ],
                $content
            );
        }

        // filters.js: el parámetro `countryOptions` queda muerto tras quitar
        // los filtros comerciales. Limpiamos la signature de la función + el
        // comentario JSDoc del módulo.
        if ($basename === 'filters.js') {
            $content = str_replace(
                '(t, { countryOptions = [] } = {})',
                '(t)',
                $content
            );
            // Limpiar la mención de countryOptions en el JSDoc del export.
            $content = preg_replace(
                '/[\t ]*\* Schema de filtros del m[oó]dulo [\w]+\. Toma `t` y `\{ countryOptions \}`\s*\n[\t ]*\* \(las opciones del multiselect Pa[ií]s vienen del controller\)\. Mismo patr[oó]n\s*\n/u',
                " * Schema de filtros del módulo. Mismo patrón\n",
                $content
            );
        }

        return $content;
    }

    /**
     * Snippets que reemplazan cada @scaffold:anchor X. Las claves son los
     * nombres de los anchors; los valores son el contenido a insertar
     * (sin salto de línea final — preg_replace mantiene el contexto).
     */
    protected function buildAnchorInserts(): array
    {
        $newLowerPl = $this->snakePlural();
        $newLower   = Str::camel($this->module);

        return [
            // Migration: column definition after $table->string('name')->index();
            'description-column' => "            \$table->text('description')->nullable();",

            // Model fillable array entry.
            'description-fillable' => "        'description',",

            // Factory definition entry.
            'description-factory' => "            'description' => fake()->optional(0.7)->sentence(8),",

            // FormRequest validation rule.
            'description-rule' => "            'description' => ['nullable', 'string', 'max:1000'],",

            // Form.vue useForm field.
            'description-useform' => "    description: props.{$newLower}?.description ?? '',",

            // Form.vue FormItem block (Col with textarea).
            'description-formitem' => "                    <Col :xs=\"24\">\n                        <FormItem\n                            :label=\"\$t('{$newLowerPl}.description')\"\n                            :validate-status=\"form.errors.description ? 'error' : ''\"\n                            :help=\"form.errors.description\"\n                        >\n                            <Input.TextArea\n                                v-model:value=\"form.description\"\n                                :rows=\"3\"\n                                :maxlength=\"1000\"\n                                show-count\n                            />\n                        </FormItem>\n                    </Col>",

            // Show.vue DescriptionsItem.
            'description-show' => "                        <DescriptionsItem :label=\"\$t('{$newLowerPl}.description')\">{{ {$newLower}.description ?? '—' }}</DescriptionsItem>",

            // Controller payload entry.
            'description-payload' => "            'description' => \$m->description,",

            // Import: row reading + normalizeDescription helper.
            'description-import-read' => "                \$description = \$this->normalizeDescription(\$row['description'] ?? null);\n",

            'description-normalize-fn' => "    protected function normalizeDescription(mixed \$value): ?string\n    {\n        if (\$value === null) return null;\n        \$desc = trim((string) \$value);\n        return \$desc === '' ? null : mb_substr(\$desc, 0, 1000);\n    }\n",

            // Import: create call + preview entries.
            'description-import-create'  => "                        'description' => \$description,",
            'description-preview-skipped' => "                            'description' => \$description,",
            'description-preview-updated' => "                            'description' => \$description,",
            'description-preview-created' => "                            'description' => \$description,",

            // Import template: comment de description en B1 (after column letters fix).
            'description-template-comment' => "                \$commentDesc = \$sheet->getComment('B1');\n                \$commentDesc->setAuthor(__('imports.template_author'));\n                \$commentDesc->getText()->createTextRun(\n                    'Descripcion opcional del registro. Texto libre, hasta 1000 caracteres.'\n                );\n                \$commentDesc->setWidth('260pt');\n                \$commentDesc->setHeight('60pt');",

            // Import template: filas de ejemplo (reemplaza array() con [name, description, is_active]).
            'generic-template-rows' => "        return [\n            ['name', 'description', 'is_active'],\n            ['Ejemplo 1', 'Descripcion opcional del registro.', '1'],\n            ['Ejemplo 2', '', '1'],\n            ['Ejemplo 3', 'Otro detalle libre.', '0'],\n        ];",

            // Lang files: el value específico por locale lo overridea
            // applyFieldTransformations() antes de llamar processFile().
            'description-lang' => "    'description' => 'Description',",
        ];
    }

    protected function findGeneratedMigration(string $newLowerPl): ?string
    {
        $dir = base_path('database/migrations');
        $matches = [];
        foreach (File::files($dir) as $file) {
            if (str_contains($file->getFilename(), "create_{$newLowerPl}_table")) {
                $matches[] = $file->getRealPath();
            }
        }
        if (empty($matches)) return null;
        rsort($matches);
        return $matches[0];
    }

    protected function relPath(string $abs): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $abs);
    }

    /**
     * Rollback: borra archivos creados y restaura modificados.
     */
    protected function rollback(): void
    {
        foreach ($this->createdFiles as $path) {
            if (File::exists($path)) {
                File::delete($path);
                $this->line("  REVERTIDO (deleted): " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
            }
        }
        foreach ($this->modifiedFiles as $path => $original) {
            file_put_contents($path, $original);
            $this->line("  REVERTIDO (restored): " . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path));
        }
    }

    protected function printChecklist(): void
    {
        $singular    = $this->module;
        $plural      = $this->plural($singular);
        $newLowerPl  = $this->snakePlural();
        $newLower    = Str::camel($singular);
        $groupSnake  = Str::snake($this->group);

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("  MODULO {$this->module} GENERADO");
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line("  Archivos creados:     " . count($this->createdFiles));
        $this->line("  Archivos modificados: " . count($this->modifiedFiles));
        $this->newLine();

        $this->line("Ubicaciones principales:");
        $this->line("  Backend:    app/Http/Controllers/{$this->group}/{$singular}Controller.php");
        $this->line("  Service:    app/Services/{$this->group}/{$singular}Service.php");
        $this->line("  Model:      app/Models/{$singular}.php");
        $this->line("  Migration:  database/migrations/*_create_{$newLowerPl}_table.php");
        $this->line("  Factory:    database/factories/{$singular}Factory.php");
        $this->line("  Frontend:   resources/js/Pages/{$plural}/  (6 paginas + 5 configs)");
        $this->line("  Components: resources/js/Components/{$plural}/  (13 componentes)");
        $this->line("  Tests:      tests/Feature/{$this->group}/{$plural}/");
        $this->line("  Routes:     routes/{$groupSnake}.php  (bloque appendeado)");
        $this->line("  Config:     config/{$newLowerPl}.php");
        $this->line("  Lang:       resources/lang/{es,en,pt}/{$newLowerPl}.php");
        $this->newLine();

        $this->warn("PASOS MANUALES OBLIGATORIOS (sin esto el modulo no funciona):");
        $this->newLine();

        $this->line("  1) Migrar la base de datos");
        $this->line("     php artisan migrate");
        $this->line("     Si la migracion falla, revisa el archivo generado y reintenta.");
        $this->newLine();

        $this->line("  2) Permisos Spatie en el seeder de roles");
        $this->line("     Editar: database/seeders/RolesAndPermissionsSeeder.php");
        $this->line("     Agregar los 4 permisos basicos al array de permisos:");
        $this->line("         '{$newLowerPl}.view'");
        $this->line("         '{$newLowerPl}.create'");
        $this->line("         '{$newLowerPl}.edit'");
        $this->line("         '{$newLowerPl}.delete'");
        $this->line("     Asignar al rol super (y a admin/user segun corresponda).");
        $this->line("     Luego correr: php artisan db:seed --class=RolesAndPermissionsSeeder");
        $this->newLine();

        $this->line("  3) Sidebar (icono + entrada en el menu lateral)");
        $this->line("     resources/js/Layouts/AppLayout.vue");
        $this->line("       Importar el icono deseado de @ant-design/icons-vue.");
        $this->line("       Agregar el item en el array correspondiente al grupo:");
        $this->line("         { key: '{$newLowerPl}',");
        $this->line("           label: t('sidebar.{$newLowerPl}'),");
        $this->line("           icon: TuIcono,");
        $this->line("           href: route('{$groupSnake}.{$newLowerPl}.index'),");
        $this->line("           inertia: true,");
        $this->line("           visible: () => can('{$newLowerPl}.view') }");
        $this->line("     Agregar la traduccion en los archivos de lang (es, en):");
        $this->line("       resources/lang/es/sidebar.php  =>  '{$newLowerPl}' => 'Nombre en espanol'");
        $this->line("       resources/lang/en/sidebar.php  =>  '{$newLowerPl}' => 'Name in english'");
        $this->newLine();

        $this->line("  4) Verificar build y limpieza de cache");
        $this->line("     npm run build");
        $this->line("     php artisan config:clear");
        $this->line("     php artisan route:clear");
        $this->newLine();

        $this->warn("PASOS RECOMENDADOS (segun el dominio del modulo):");
        $this->newLine();

        $this->line("  5) Columnas del dominio en la migracion");
        $this->line("     El scaffold solo trae 'name' (required) + 'description' (nullable).");
        $this->line("     Editar la migracion para sumar campos especificos:");
        $this->line("         price, stock, sku, birth_date, FKs, etc.");
        $this->line("     Tambien sumarlas al fillable del modelo, casts, factory,");
        $this->line("     Form.vue, Show.vue, columns.js y las reglas de los FormRequests.");
        $this->newLine();

        $this->line("  6) Relaciones del modelo (FKs salientes)");
        $this->line("     Si el modulo tiene FKs hacia otras tablas, agregar el metodo");
        $this->line("     belongsTo() correspondiente en app/Models/{$singular}.php");
        $this->line("     y cargar la relacion con with() en el Service.");
        $this->newLine();

        $this->line("  7) Dependientes (FKs entrantes a este modulo)");
        $this->line("     Si OTROS modelos referencian a {$singular} con FK, declarar el");
        $this->line("     metodo dependents() para que el sistema avise antes de borrar:");
        $this->line("       app/Models/{$singular}.php  =>  public function dependents(): array");
        $this->newLine();

        $this->line("  8) Plan gating (si el modulo es premium)");
        $this->line("     Si el modulo debe estar disponible solo en planes pro/enterprise:");
        $this->line("       a) Sumar la feature en config/features.php (matrix de planes).");
        $this->line("       b) Aplicar middleware('plan_feature:nombre_feature') en las");
        $this->line("          rutas correspondientes en routes/{$groupSnake}.php");
        $this->line("       c) Sumar canUsePlanFeature() al visible del item del sidebar.");
        $this->newLine();

        $this->line("  9) Filtros avanzados (opcional)");
        $this->line("     Para usar el query builder de filtros avanzados, declarar:");
        $this->line("       app/Models/{$singular}.php  =>  public static function filterSchema(): array");
        $this->line("     Ejemplo en app/Models/Customer.php (master template).");
        $this->newLine();

        $this->line(" 10) Data source para Automatizaciones (opcional)");
        $this->line("     Si quieres que las automatizaciones puedan consultar este modulo:");
        $this->line("       a) Crear app/Services/Automations/DataSources/{$plural}DataSource.php");
        $this->line("          implementando DataSourceContract.");
        $this->line("       b) Registrarlo en DataSourceRegistry::register().");
        $this->line("     Detalles en docs/AUTOMATIONS.md (seccion 8).");
        $this->newLine();

        $this->line(" 11) Capa API REST (opcional — el scaffold NO la genera)");
        $this->line("     Por defecto los modulos generados son web-only (Inertia).");
        $this->line("     Si necesitas exponer el modulo via API REST:");
        $this->line("       a) Crear app/Http/Resources/{$singular}Resource.php");
        $this->line("          (mirar CustomerResource.php como referencia).");
        $this->line("       b) Crear app/Http/Controllers/Api/V1/{$singular}ApiController.php");
        $this->line("          (mirar CustomerApiController.php como referencia).");
        $this->line("       c) Agregar las rutas en routes/api.php con abilities Sanctum:");
        $this->line("          {$newLowerPl}:read / {$newLowerPl}:write / {$newLowerPl}:delete");
        $this->line("       d) Documentar con anotaciones Scribe y regenerar docs:");
        $this->line("          php artisan scribe:generate");
        $this->newLine();

        $this->line(" 12) Tests");
        $this->line("     El scaffold clona los tests si Customer los tiene. Verificar:");
        $this->line("       php artisan test --filter={$singular}");
        $this->newLine();

        $this->info("URL del modulo nuevo: /{$groupSnake}/{$newLowerPl}");
        $this->info("Documentacion completa: docs/CREATE-MODULE.md");
        $this->info('═══════════════════════════════════════════════════════════');
    }
}
